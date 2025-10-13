#!/bin/bash

# EC2 Deployment Script for OXEX with Supabase
# This script runs on the EC2 instance to deploy the container

set -e

echo "🚀 Starting OXEX deployment on EC2..."

# 1. Login to ECR
echo "Logging into ECR..."
aws ecr get-login-password --region eu-west-2 | sudo docker login --username AWS --password-stdin 476488973086.dkr.ecr.eu-west-2.amazonaws.com

# 2. Stop and remove old container
echo "Stopping and removing old container..."
sudo docker stop oxex-app 2>/dev/null || true
sudo docker rm oxex-app 2>/dev/null || true

# 3. Pull latest image
echo "Pulling latest image..."
sudo docker pull 476488973086.dkr.ecr.eu-west-2.amazonaws.com/oxex-app:latest

# 4. Run new container with Supabase environment variables
echo "Starting new container with Supabase configuration..."
sudo docker run -d \
  --name oxex-app \
  --restart unless-stopped \
  -p 80:80 \
  -e SUPABASE_HOST=aws-1-eu-west-2.pooler.supabase.com \
  -e SUPABASE_PORT=5432 \
  -e SUPABASE_USER=postgres.yexgrelshvapsihllnmn \
  -e SUPABASE_PASSWORD='kCiBky+kcN%*7YF' \
  -e SUPABASE_DATABASE=postgres \
  -e SUPABASE_DB_HOST=aws-1-eu-west-2.pooler.supabase.com \
  -e SUPABASE_DB_PORT=5432 \
  -e SUPABASE_DB_NAME=postgres \
  -e SUPABASE_DB_USER=postgres.yexgrelshvapsihllnmn \
  -e SUPABASE_DB_PASSWORD='kCiBky+kcN%*7YF' \
  -e SUPABASE_DB_SSLMODE=require \
  476488973086.dkr.ecr.eu-west-2.amazonaws.com/oxex-app:latest

# 5. Verify deployment
echo "Verifying deployment..."
sudo docker ps

echo "✅ Deployment completed!"
echo "🌐 Your application is available at: http://18.168.117.152/oxex-admin/pass_standard_detail.php"
