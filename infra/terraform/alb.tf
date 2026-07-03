# Internet-facing ALB for staging. HTTP :80 ONLY UNTIL a domain + ACM certificate exist —
# acceptable for a staging smoke surface, a hard blocker for production (TLS + WAF required
# before any production listener). Tracked as a known gap.

resource "aws_security_group" "alb" {
  name        = "unero-${var.environment}-identity-alb"
  description = "identity-service ALB: HTTP from internet (staging), egress to app tier only."
  vpc_id      = data.terraform_remote_state.platform.outputs.vpc_id

  ingress {
    description = "HTTP from anywhere (staging only; TLS before production)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description     = "To identity tasks on the container port"
    from_port       = var.container_port
    to_port         = var.container_port
    protocol        = "tcp"
    security_groups = [data.terraform_remote_state.platform.outputs.app_security_group_id]
  }

  tags = { Name = "unero-${var.environment}-identity-alb" }
}

# App tier accepts traffic from this ALB on the container port (the shared app SG ships
# with no ingress; each service opens exactly what it needs).
resource "aws_security_group_rule" "app_from_alb" {
  type                     = "ingress"
  description              = "identity-service: from its ALB"
  from_port                = var.container_port
  to_port                  = var.container_port
  protocol                 = "tcp"
  security_group_id        = data.terraform_remote_state.platform.outputs.app_security_group_id
  source_security_group_id = aws_security_group.alb.id
}

resource "aws_lb" "this" {
  name               = "unero-${var.environment}-identity"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = data.terraform_remote_state.platform.outputs.public_subnet_ids

  drop_invalid_header_fields = true

  tags = { Name = "unero-${var.environment}-identity" }
}

resource "aws_lb_target_group" "this" {
  name        = "unero-${var.environment}-identity"
  port        = var.container_port
  protocol    = "HTTP"
  vpc_id      = data.terraform_remote_state.platform.outputs.vpc_id
  target_type = "ip"

  deregistration_delay = 30

  health_check {
    path                = var.health_check_path
    matcher             = "200"
    interval            = 15
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 3
  }

  tags = { Name = "unero-${var.environment}-identity" }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.this.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.this.arn
  }
}
