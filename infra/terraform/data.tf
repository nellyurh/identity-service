# Platform substrate this service composes onto — read, never owned.
data "terraform_remote_state" "platform" {
  backend = "s3"
  config = {
    bucket = "unero-terraform-state"
    key    = "environments/${var.environment}/terraform.tfstate"
    region = "af-south-1"
  }
}

data "aws_caller_identity" "current" {}
