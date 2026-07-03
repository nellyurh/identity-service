locals {
  platform    = data.terraform_remote_state.platform.outputs
  secret_arns = local.platform.identity_secret_arns
}

resource "aws_ecs_task_definition" "this" {
  family                   = "unero-${var.environment}-identity-service"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.cpu
  memory                   = var.memory
  execution_role_arn       = local.platform.identity_execution_role_arn
  task_role_arn            = local.platform.identity_task_role_arn

  container_definitions = jsonencode([
    {
      name      = "identity-service"
      image     = var.image_digest
      essential = true

      portMappings = [{ containerPort = var.container_port, protocol = "tcp" }]

      environment = [
        { name = "APP_ENV", value = var.environment },
        { name = "APP_DEBUG", value = "false" },
        { name = "LOG_CHANNEL", value = "stderr" },
        { name = "DB_HOST", value = local.platform.aurora_endpoint },
        { name = "DB_PORT", value = "5432" },
        { name = "DB_DATABASE", value = "unero" },
        { name = "DB_SSLMODE", value = "require" },
        { name = "DB_SCHEMA", value = "identity" },
        { name = "REDIS_HOST", value = local.platform.redis_endpoint },
        { name = "REDIS_PORT", value = "6379" },
        { name = "REDIS_SCHEME", value = "tls" },
        { name = "EVENT_BUS_NAME", value = local.platform.event_bus_name },
        { name = "AWS_DEFAULT_REGION", value = var.region },
      ]

      # Values injected by ECS from Secrets Manager at task start (ADR-017), mapped to the
      # EXACT env names the app reads (config/database.php, config/unero.php). JSON-key
      # extraction (arn:key::) pulls individual fields from JSON secrets.
      secrets = [
        { name = "DB_USERNAME", valueFrom = "${local.secret_arns["database"]}:username::" },
        { name = "DB_PASSWORD", valueFrom = "${local.secret_arns["database"]}:password::" },
        { name = "REDIS_PASSWORD", valueFrom = local.secret_arns["redis"] },
        { name = "IDENTITY_JWT_KID", valueFrom = "${local.secret_arns["jwt-signing-keys"]}:kid::" },
        { name = "IDENTITY_JWT_PRIVATE_KEY", valueFrom = "${local.secret_arns["jwt-signing-keys"]}:private_key::" },
        { name = "IDENTITY_JWT_PUBLIC_KEY", valueFrom = "${local.secret_arns["jwt-signing-keys"]}:public_key::" },
        { name = "APP_KEY", valueFrom = local.secret_arns["app-key"] },
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = local.platform.identity_log_group_name
          awslogs-region        = var.region
          awslogs-stream-prefix = "identity"
        }
      }

      # Container-level healthcheck intentionally omitted: the Dockerfile defines its own
      # (php file_get_contents on /healthz); the ALB target-group check covers routing health.
    }
  ])
}

resource "aws_ecs_service" "this" {
  name            = "identity-service"
  cluster         = local.platform.ecs_cluster_name
  task_definition = aws_ecs_task_definition.this.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = local.platform.app_subnet_ids
    security_groups  = [local.platform.app_security_group_id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.this.arn
    container_name   = "identity-service"
    container_port   = var.container_port
  }

  health_check_grace_period_seconds = 90

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  depends_on = [aws_lb_listener.http]
}
