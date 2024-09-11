oc project $OC_PROJECT

echo "Deploying secrets to: $OC_PROJECT..."

# Check if the Helm deployment exists
if oc get secret $APP_NAME-secrets | grep -q "NotFound"; then
  echo "Secrets not found... creating..."

  echo "
    kind: Secret
    apiVersion: v1
    metadata:
      name: $APP_NAME-secrets
      namespace: $OC_PROJECT
      labels:
        template: $APP_NAME
      data:
        database-name: $DATABASE_NAME
        database-password: $SECRET_DB_PASSWORD
        database-user: $DB_USER
      type: Opaque
    " > secrets.yaml
  oc create secret generic $APP_NAME-secrets --from-file=secrets.yaml

else
  echo "Secrets already exist. Moving on..."
fi
