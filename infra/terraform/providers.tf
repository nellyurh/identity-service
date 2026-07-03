provider "aws" {
  region = var.region
  default_tags {
    tags = {
      Platform    = "unero"
      Environment = var.environment
      Service     = "identity-service"
      ManagedBy   = "terraform"
      Repo        = "identity-service"
    }
  }
}
