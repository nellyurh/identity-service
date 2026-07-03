terraform {
  required_version = ">= 1.7.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.60"
    }
  }
  backend "s3" {
    bucket         = "unero-terraform-state"
    key            = "services/identity-service/staging/terraform.tfstate"
    region         = "af-south-1"
    dynamodb_table = "unero-terraform-locks"
    encrypt        = true
  }
}
