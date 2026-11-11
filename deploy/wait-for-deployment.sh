#!/bin/bash
#
# Wait for a CodeDeploy deployment to finish, and fail if it did. Without this, GitHub Actions
# only knows that the deployment was started, so a deployment that fails on the server (e.g.,
# because codedeploy-agent isn't running) still shows as a passing build.
#
# Usage: wait-for-deployment.sh DEPLOYMENT_ID

deployment_id="$1"
if [ -z "$deployment_id" ]; then
    echo "Usage: $0 DEPLOYMENT_ID"
    exit 1
fi

echo "Waiting for deployment $deployment_id to finish..."

# Checks every 15 seconds, for up to 30 minutes.
if aws deploy wait deployment-successful --deployment-id "$deployment_id"; then
    echo "Deployment $deployment_id succeeded."
    exit 0
fi

echo "::error::Deployment $deployment_id failed."

# The deployment's own error is generic ("too many individual instances failed"), so also show
# why each server failed.
aws deploy get-deployment --deployment-id "$deployment_id" \
    --query 'deploymentInfo.[status, errorInformation.message]' --output text || true
for target in $(aws deploy list-deployment-targets --deployment-id "$deployment_id" \
    --query 'targetIds' --output text 2>/dev/null); do
    aws deploy get-deployment-target --deployment-id "$deployment_id" --target-id "$target" \
        --query 'deploymentTarget.instanceTarget.lifecycleEvents[?status==`Failed`].[lifecycleEventName, diagnostics.message, diagnostics.logTail]' \
        --output text | sed "s/^/$target: /" || true
done

exit 1
