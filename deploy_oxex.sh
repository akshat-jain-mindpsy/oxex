#!/bin/bash

# OXEX Docker Deployment Script
# This script builds, pushes Docker image to ECR, then deploys to EC2 with Supabase

set -e

echo "🚀 Starting OXEX Docker Deployment Process"
echo "=========================================="

# Step 1: Build and push Docker image
echo "Step 1: Building and pushing Docker image..."
./docker_imf_build.sh

# Step 2: Copy deployment script to EC2
echo "Step 2: Copying deployment script to EC2..."
scp -i ~/.ssh/oxex-key.pem image_build.sh ec2-user@ec2-18-168-117-152.eu-west-2.compute.amazonaws.com:~/

# Step 3: SSH to EC2 and run deployment
echo "Step 3: Deploying to EC2..."
ssh -i ~/.ssh/oxex-key.pem ec2-user@ec2-18-168-117-152.eu-west-2.compute.amazonaws.com 'chmod +x ~/image_build.sh && ~/image_build.sh'

echo "✅ Deployment completed successfully!"
echo "🌐 Your application is available at: http://18.168.117.152/oxex-admin/pass_standard_detail.php"