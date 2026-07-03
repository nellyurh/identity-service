variable "environment" {
  type    = string
  default = "staging"
}

variable "region" {
  type    = string
  default = "af-south-1"
}

# ---- CONFIRM against the identity-service repo before first apply ----
variable "container_port" {
  description = "Port the container listens on (php-fpm/nginx or Octane). CONFIRM."
  type        = number
  default     = 8080
}

variable "health_check_path" {
  description = "ALB target health check path. Design doc: GET /health/ready. CONFIRM route exists."
  type        = string
  default     = "/healthz"
}
# ----------------------------------------------------------------------

variable "image_digest" {
  description = "Full image reference BY DIGEST (repo@sha256:...), per platform standard. Supplied by the deploy workflow."
  type        = string
}

variable "desired_count" {
  type    = number
  default = 1
}

variable "cpu" {
  type    = number
  default = 512
}

variable "memory" {
  type    = number
  default = 1024
}
