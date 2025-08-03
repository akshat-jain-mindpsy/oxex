#!/bin/bash

# CI/CD Setup Script for OXEX Application
# This script helps you set up the required GitHub secrets

echo "🚀 OXEX CI/CD Pipeline Setup"
echo "=============================="

# Check if required tools are installed
command -v aws >/dev/null 2>&1 || { echo "❌ AWS CLI is required but not installed. Please install it first."; exit 1; }
command -v git >/dev/null 2>&1 || { echo "❌ Git is required but not installed. Please install it first."; exit 1; }

echo ""
echo "📋 Required GitHub Secrets:"
echo "============================"
echo ""

# Get AWS Account ID
echo "🔍 Getting AWS Account ID..."
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text 2>/dev/null)
if [ $? -eq 0 ]; then
    echo "✅ AWS Account ID: $AWS_ACCOUNT_ID"
else
    echo "❌ Failed to get AWS Account ID. Please configure AWS CLI first."
    echo "   Run: aws configure"
    exit 1
fi

echo ""
echo "📝 GitHub Secrets to Add:"
echo "========================="
echo ""
echo "1. AWS_ACCESS_KEY_ID - Your AWS access key"
echo "2. AWS_SECRET_ACCESS_KEY - Your AWS secret key"
echo "3. AWS_ACCOUNT_ID - $AWS_ACCOUNT_ID"
echo ""
echo "4. EC2_HOST - Your EC2 instance IP (e.g., 18.209.109.93)"
echo "5. EC2_USERNAME - SSH username (usually ec2-user)"
echo "6. EC2_SSH_KEY - Your private SSH key content"
echo ""
echo "7. MYSQL_HOST - Database host (e.g., localhost)"
echo "8. MYSQL_PORT - Database port (usually 3306)"
echo "9. MYSQL_USER - Database username"
echo "10. MYSQL_PASSWORD - Database password"
echo "11. MYSQL_DATABASE - Database name"
echo ""

# Check if SSH key exists
SSH_KEY_PATH="$HOME/.ssh/oxex-key.pem"
if [ -f "$SSH_KEY_PATH" ]; then
    echo "🔑 SSH Key found at: $SSH_KEY_PATH"
    echo "   To get the key content for EC2_SSH_KEY secret, run:"
    echo "   cat $SSH_KEY_PATH"
else
    echo "⚠️  SSH key not found at $SSH_KEY_PATH"
    echo "   Make sure you have the correct SSH key for your EC2 instance"
fi

echo ""
echo "📚 How to Add Secrets:"
echo "======================"
echo "1. Go to your GitHub repository"
echo "2. Click Settings → Secrets and variables → Actions"
echo "3. Click 'New repository secret'"
echo "4. Add each secret with the exact name and value"
echo ""

echo "🧪 Testing Setup:"
echo "================="
echo "After adding all secrets, test the pipeline with:"
echo "  git add ."
echo "  git commit -m 'Add CI/CD pipeline'"
echo "  git push origin main"
echo ""

echo "📊 Monitor Progress:"
echo "==================="
echo "Check the Actions tab in your GitHub repository to monitor deployment progress."
echo ""

echo "🔧 Troubleshooting:"
echo "=================="
echo "If the pipeline fails:"
echo "1. Check all secrets are correctly set"
echo "2. Verify AWS credentials have ECR permissions"
echo "3. Ensure EC2 instance is running and accessible"
echo "4. Check Docker is installed on EC2"
echo ""

echo "✅ Setup complete! Add the secrets to GitHub and push to trigger the pipeline." 