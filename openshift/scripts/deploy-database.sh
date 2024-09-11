oc project $OC_PROJECT

if [[ `oc describe configmap $DB_POD_NAME 2>&1` =~ "NotFound" ]]; then
  # Create configmap from the resources directory
  oc create configmap $DB_POD_NAME --from-file=./openshift/config/mariadb/resources
fi

if [[ `oc describe sts $DB_POD_NAME 2>&1` =~ "NotFound" ]]; then
  echo "$DB_POD_NAME NOT FOUND: Beginning deployment..."
  envsubst < ./openshift/config/mariadb/config.yaml | oc create -f - -n $DEPLOY_NAMESPACE
else
  echo "$DB_POD_NAME Installation found...Scaling to 0..."
  oc scale sts/$DB_POD_NAME --replicas=0

  ATTEMPTS=0
  MAX_ATTEMPTS=60
  while [[ $(oc get sts $DB_POD_NAME -o jsonpath='{.status.replicas}') -ne 0 && $ATTEMPTS -ne $MAX_ATTEMPTS ]]; do
    echo "Waiting for $DB_POD_NAME to scale to 0..."
    sleep 10
    ATTEMPTS=$((ATTEMPTS + 1))
  done
  if [[ $ATTEMPTS -eq $MAX_ATTEMPTS ]]; then
    echo "Timeout waiting for $DB_POD_NAME to scale to 0"
    exit 1
  fi

  echo "Recreating $DB_POD_NAME from image: $IMAGE_REPO$DB_IMAGE"
  oc delete sts $DB_POD_NAME -n $DEPLOY_NAMESPACE
  oc delete configmap $DB_POD_NAME-config -n $DEPLOY_NAMESPACE
  oc delete service $DB_POD_NAME -n $DEPLOY_NAMESPACE
  # Substitute variables in the config.yaml file and create the deployment
  envsubst < ./openshift/config/mariadb/config.yaml | oc create -f - -n $DEPLOY_NAMESPACE

  sleep 10

  oc scale sts/$DB_POD_NAME --replicas=1

  sleep 15

  # Wait for the deployment to scale to 1
  ATTEMPTS=0
  MAX_ATTEMPTS=60
  while [[ $(oc get sts $DB_POD_NAME -o jsonpath='{.status.replicas}') -ne 1 && $ATTEMPTS -ne $MAX_ATTEMPTS ]]; do
    echo "Waiting for $DB_POD_NAME to scale to 1..."
    sleep 10
    ATTEMPTS=$((ATTEMPTS + 1))
  done
  if [[ $ATTEMPTS -eq $MAX_ATTEMPTS ]]; then
    echo "Timeout waiting for $DB_POD_NAME to scale to 1"
    exit 1
  fi
fi

echo "Checking if the database is online and contains expected data..."
ATTEMPTS=0
WAIT_TIME=10
MAX_ATTEMPTS=30 # wait up to 5 minutes

# Get the name of the first pod in the StatefulSet
DB_POD_NAME=""
until [ -n "$DB_POD_NAME" ]; do
  ATTEMPTS=$(( $ATTEMPTS + 1 ))
  DB_POD_NAME=$(oc get pods -l app=$DB_POD_NAME -o jsonpath='{.items[?(@.status.phase=="Running")].metadata.name}')

  if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
    echo "Timeout waiting for the pod to have status.phase:Running. Exiting..."
    exit 1
  fi

  if [ -z "$DB_POD_NAME" ]; then
    echo "Waiting for the database pod to be ready... $(($ATTEMPTS * $WAIT_TIME)) seconds..."
    sleep $WAIT_TIME
  fi
done

echo "Database pod name: $DB_POD_NAME has been found and is running."

ATTEMPTS=0

until [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
  ATTEMPTS=$(( $ATTEMPTS + 1 ))
  echo "Waiting for database to come online... $(($ATTEMPTS * $WAIT_TIME)) seconds..."

  # Capture the output of the mariadb command
  OUTPUT=$(oc exec $DB_POD_NAME -- bash -c "mariadb -u root -e 'USE $DB_POD_NAME; SELECT COUNT(*) FROM user;'" 2>&1)

  # Check if the output contains an error
  if echo "$OUTPUT" | grep -qi "error"; then
    echo "❌ Database error: $OUTPUT"
    # exit 1
  fi

  # Extract the user count from the output
  CURRENT_USER_COUNT=$(echo "$OUTPUT" | grep -oP '\d+')

  if [ $CURRENT_USER_COUNT -gt 0 ]; then
    echo "Database is online and contains $CURRENT_USER_COUNT users."
    break
  else
    echo "Database is offline. Attempt $ATTEMPTS out of $MAX_ATTEMPTS."
    sleep $WAIT_TIME
  fi
done

if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
  echo "❌ Timeout waiting for the database to be online. Exiting..."
  exit 1
fi

echo "$DB_POD_NAME Database deployment is complete."
