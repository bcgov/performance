#!/bin/bash

# Ensure APP_NAME is set
if [ -z "$APP_NAME" ]; then
  echo "Error: APP_NAME is not set."
  exit 1
fi

echo "Deploying database backups for $APP_NAME to: $DB_BACKUP_DEPLOYMENT_NAME..."

#!/bin/bash

# Debugging: Print environment variables
echo "DB_BACKUP_DEPLOYMENT_NAME: $DB_BACKUP_DEPLOYMENT_NAME"
echo "APP_NAME: $APP_NAME"
echo "DB_HOST: $DB_HOST"
echo "DB_PORT: $DB_PORT"
echo "DB_DATABASE: $DB_DATABASE"
echo "BACKUP_HELM_CHART: $BACKUP_HELM_CHART"
echo "DB_BACKUP_IMAGE: $DB_BACKUP_IMAGE"
echo "DB_BACKUP_DEPLOYMENT_FULL_NAME: $DB_BACKUP_DEPLOYMENT_FULL_NAME"

# Ensure APP_NAME is set
if [ -z "$APP_NAME" ]; then
  echo "Error: APP_NAME is not set."
  exit 1
fi

echo "Deploying database backups to: $DB_BACKUP_DEPLOYMENT_NAME..."

# Function to extract and display backup information
extract_backup_info() {
  local BACKUP_LIST=$1

  # Extract database name and current size
  DATABASE_NAME=$(echo "$BACKUP_LIST" | grep -oP 'Database:\s+\K\S+')
  CURRENT_SIZE=$(echo "$BACKUP_LIST" | grep -oP 'Current Size:\s+\K\S+')

  # Extract size, used, avail, use%, and mounted on
  SIZE=$(echo "$BACKUP_LIST" | grep -oP 'Size:\s+\K\S+')
  USED=$(echo "$BACKUP_LIST" | grep -oP 'Used:\s+\K\S+')
  AVAIL=$(echo "$BACKUP_LIST" | grep -oP 'Avail:\s+\K\S+')
  USE_PERCENT=$(echo "$BACKUP_LIST" | grep -oP 'Use%:\s+\K\S+')
  MOUNTED_ON=$(echo "$BACKUP_LIST" | grep -oP 'Mounted on:\s+\K\S+')

  # Display extracted information
  echo "Database: $DATABASE_NAME"
  echo "Current Size: $CURRENT_SIZE"
  echo "Size: $SIZE"
  echo "Used: $USED"
  echo "Avail: $AVAIL"
  echo "Use%: $USE_PERCENT"
  echo "Mounted on: $MOUNTED_ON"

  # Prepend mounted on value to DB_INIT_FILE_LOCATION
  DB_INIT_FILE_LOCATION="$MOUNTED_ON/$DB_INIT_FILE_LOCATION"
  echo "Updated DB_INIT_FILE_LOCATION: $DB_INIT_FILE_LOCATION"

  # Add notice if Use% is greater than 70% or less than 1%
  USE_PERCENT_VALUE=$(echo "$USE_PERCENT" | tr -d '%')
  if [ "$USE_PERCENT_VALUE" -gt 70 ]; then
    echo "Notice: Use% is greater than 70%."
  elif [ "$USE_PERCENT_VALUE" -lt 1 ]; then
    echo "Notice: Use% is less than 1%."
  fi
}

# Function to restore the backup by filename
restore_backup_from_file() {
  local FILENAME=$1
  echo "Restoring backup from file: $FILENAME"

  # Check the file extension and run the appropriate restore command
  if [[ "$FILENAME" == *.gz ]]; then
    # Run the restore command for .gz files
    oc exec $(oc get pod -l app.kubernetes.io/name=backup-storage -o jsonpath='{.items[0].metadata.name}') -- ./backup.sh -r $DB_NAME/$DB_DATABASE -f "$FILENAME"
  elif [[ "$FILENAME" == *.sql ]]; then
    # Run the SQL restore command for .sql files
    oc exec $(oc get pod -l app.kubernetes.io/name=backup-storage -o jsonpath='{.items[0].metadata.name}') -- bash -c "mysql -h $DB_HOST -u root performance < $FILENAME"
  else
    echo "Unsupported file type: $FILENAME"
  fi

  echo "Backup restoration process completed."
}

# Function to list available backups
list_backups() {
  # Connect to the backup pod and list available backups
  # echo "Checking if the database ($DB_HOST) is online and contains expected data..."
  ATTEMPTS=0
  WAIT_TIME=10
  MAX_ATTEMPTS=30 # wait up to 5 minutes
  BACKUP_POD=""
  DB_INIT_FILE_LOCATION="/backups/$DB_INIT_FILE_LOCATION"

  until [ -n "$BACKUP_POD" ]; do
    ATTEMPTS=$(( $ATTEMPTS + 1 ))
    BACKUP_POD=$(oc get pod -l app.kubernetes.io/name=backup-storage -o jsonpath='{.items[0].metadata.name} 2>/dev/null')

    if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
      BACKUP_POD="Timeout waiting for the backup pod to be running."
      exit 1
    fi

    if [ -z "$BACKUP_POD" ]; then
      # echo "No pods found in Running state ($BACKUP_POD). Retrying in $WAIT_TIME seconds..."
      sleep $WAIT_TIME
    fi
  done

  echo "Backup pod found and running: $BACKUP_POD." >&2

  BACKUP_LIST=$(oc exec $BACKUP_POD -- ./backup.sh -l)

  # Extract and display backup information
  # extract_backup_info "$BACKUP_LIST"

  # Check if the backup list contains the remote backup file location
  if ! echo "$BACKUP_LIST" | grep -q "$DB_INIT_FILE_LOCATION"; then
    echo "Database initialization file NOT FOUND in the backup list ($DB_INIT_FILE_LOCATION)." >&2
    # echo "Copying the local file to the backup pod..." >&2
    # if ! oc cp --retries=25 "$LOCAL_SQL_INIT_FILE" "$BACKUP_POD:$REMOTE_BACKUP_FILE_LOCATION"; then
    #   echo "Error: Failed to copy the local file to the backup pod." >&2
    #   exit 1
    # fi
    # echo "File copied successfully." >&2
  fi

  # Parse the backup list into an array
  IFS=$'\n' read -rd '' -a BACKUP_ARRAY <<< "$BACKUP_LIST"

  # Filter and process the backup list
  FILTERED_SORTED_BACKUPS=$(echo "$BACKUP_LIST" | awk '
    BEGIN { skip = 1 }
    /^--------------------------------------------------------------------------------------------------------------------------------$/ { skip = 0; next }
    skip { next }
    NF == 4 { print $0 }
  ' | sort -k2,3r)

  # Select the latest backup
  LATEST_BACKUP=$(echo "$FILTERED_SORTED_BACKUPS" | head -n 1)

  # Debugging: Print latest backup
  echo "Latest backup: $LATEST_BACKUP" >&2

  # Check if the remote backup file location is newer
  if [[ -n "$REMOTE_BACKUP_FILE_LOCATION" && "$REMOTE_BACKUP_FILE_LOCATION" -nt "$LATEST_BACKUP" ]]; then
    LATEST_BACKUP="$REMOTE_BACKUP_FILE_LOCATION"
  fi

  # Return the filename of the selected backup
  echo "$LATEST_BACKUP" | awk '{print $4}'
}

restore_database_from_backup() {
  echo "Attempting to restore the database from the latest backup..."

  # List backups and get the filename of the latest backup
  echo "Listing available backups..."
  LATEST_BACKUP_FILENAME=$(list_backups)

  # Check if the file exists
  if [[ -f "$LATEST_BACKUP_FILENAME" ]]; then
    # Restore the backup using the filename
    restore_backup_from_file "$LATEST_BACKUP_FILENAME"
  else
    echo "Backup file: $LATEST_BACKUP_FILENAME does not exist. Skipping restore."
  fi
}

oc project $OC_PROJECT

helm repo add bcgov http://bcgov.github.io/helm-charts
helm repo update

# Check if the Helm deployment exists
if helm list -q | grep -q "^$DB_BACKUP_DEPLOYMENT_NAME$"; then
  echo "Helm deployment found. Updating..."

  # Create a temporary values file with the updated backupConfig
  cat <<EOF > temp-values.yaml
backupConfig: |
  mariadb=$DB_HOST:$DB_PORT/$DB_DATABASE
  0 1 * * * default ./backup.sh -s
  0 4 * * * default ./backup.sh -s -v all
EOF

  # Upgrade the Helm deployment with the new values
  if [[ `helm upgrade $DB_BACKUP_DEPLOYMENT_NAME $BACKUP_HELM_CHART --reuse-values -f temp-values.yaml 2>&1` =~ "Error" ]]; then
    echo "Backup container update FAILED."
    exit 1
  fi

  # Clean up the temporary values file
  rm temp-values.yaml

  if [[ `oc describe deployment $DB_BACKUP_DEPLOYMENT_FULL_NAME 2>&1` =~ "NotFound" ]]; then
    echo "Backup Helm exists, but deployment NOT FOUND."
    exit 1
  else
    echo "Backup deployment FOUND. Updating..."
    oc set image deployment/$DB_BACKUP_DEPLOYMENT_FULL_NAME backup-storage=$BACKUP_IMAGE
  fi
  echo "Backup container updates completed."
else
  echo "Helm $DB_BACKUP_DEPLOYMENT_NAME NOT FOUND. Beginning deployment..."
  echo "
    image:
      repository: \"$BACKUP_HELM_CHART\"
      pullPolicy: Always
      tag: dev

    persistence:
      backup:
        accessModes: [\"ReadWriteMany\"]
        storageClassName: netapp-file-standard
      verification:
        storageClassName: netapp-file-standard

    backupConfig: |
      mariadb=$DB_HOST:$DB_PORT/$DB_DATABASE
      0 1 * * * default ./backup.sh -s
      0 4 * * * default ./backup.sh -s -v all

    db:
      secretName: $APP_NAME-secrets
      usernameKey: database-user
      passwordKey: database-password

    env:
      DATABASE_SERVICE_NAME:
        value: \"$DB_HOST\"
      ENVIRONMENT_FRIENDLY_NAME:
        value: \"$APP_NAME Backups\"
    " > backup-config.yaml
  # helm install $DB_BACKUP_DEPLOYMENT_NAME $BACKUP_HELM_CHART --atomic --wait -f backup-config.yaml
  helm install $DB_BACKUP_DEPLOYMENT_NAME $BACKUP_HELM_CHART -f backup-config.yaml
  echo "Waiting for backup installation..."
  # For some reason the defaault image doesn't work, and we prefer the mariadb image anyway
  echo "Setting backup deployment image to: $BACKUP_IMAGE ..."
  oc set image deployment/$DB_BACKUP_DEPLOYMENT_FULL_NAME backup-storage=$BACKUP_IMAGE
  # Set best-effort resource limits for the backup deployment
  echo "Setting best-effort resource limits for the backup deployment..."
  oc set resources deployment/$DB_BACKUP_DEPLOYMENT_FULL_NAME --limits=cpu=0,memory=0 --requests=cpu=0,memory=0
fi

# Function to wait for a statefulset to be ready
wait_for_statefulset() {
  local STATEFULSET_NAME=$1
  local ATTEMPTS=0
  local MAX_ATTEMPTS=30
  local WAIT_TIME=10

  until oc rollout status statefulset/$STATEFULSET_NAME | grep -q "successfully rolled out"; do
    ATTEMPTS=$((ATTEMPTS + 1))

    if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
      echo "Timeout waiting for the $STATEFULSET_NAME statefulset to be ready."
      exit 1
    fi

    echo "Waiting for statefulset $STATEFULSET_NAME to be ready. Retrying in $WAIT_TIME seconds..."
    sleep $WAIT_TIME
  done

  echo "Statefulset $STATEFULSET_NAME is ready."
}

# Function to wait for a deployment to be ready
wait_for_rollout() {
  local DEPLOYMENT_NAME=$1
  local DEPLOYMENT_TYPE=$2
  local ATTEMPTS=0
  local MAX_ATTEMPTS=30
  local WAIT_TIME=10

  until oc rollout status $DEPLOYMENT_TYPE/$DEPLOYMENT_NAME | grep -q "successfully rolled out"; do
    ATTEMPTS=$((ATTEMPTS + 1))

    if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
      echo "Timeout waiting for the $DEPLOYMENT_NAME deployment to be ready."
      exit 1
    fi

    echo "Waiting for $DEPLOYMENT_TYPE/$DEPLOYMENT_NAME to be ready. Retrying in $WAIT_TIME seconds..."
    sleep $WAIT_TIME
  done

  echo "Roll-out of $DEPLOYMENT_NAME is ready."
}

echo "Checking if the database ($DB_HOST) is online and contains expected data..."

ATTEMPTS=0
WAIT_TIME=10
MAX_ATTEMPTS=30 # wait up to 5 minutes

# Get the name of the first pod in the StatefulSet
DB_POD_NAME=""
until [ -n "$DB_POD_NAME" ]; do
  ATTEMPTS=$(( $ATTEMPTS + 1 ))
  PODS=$(oc get pods -l app=$DB_HOST --field-selector=status.phase=Running -o jsonpath='{.items[*].metadata.name}')

  if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
    echo "Timeout waiting for the pod to have status.phase:Running. Exiting..."
    exit 1
  fi

  if [ -z "$PODS" ]; then
    echo "No [app=$DB_HOST] pods found in Running state. Retrying in $WAIT_TIME seconds..."
    sleep $WAIT_TIME
  else
    DB_POD_NAME=$(echo $PODS | awk '{print $1}')
  fi
done

echo "Database pod found and running: $DB_POD_NAME."

TOTAL_USER_COUNT=0
CURRENT_USER_COUNT=0
DATABASE_IS_ONLINE=0
ATTEMPTS=0
OUTPUT=""
until [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
  ATTEMPTS=$(( $ATTEMPTS + 1 ))
  echo "Waiting for database to come online... $(($ATTEMPTS * $WAIT_TIME)) seconds..."

  # Capture the output of the mariadb command
  OUTPUT=$(oc exec $DB_POD_NAME -- bash -c "mariadb -u root -e 'USE $DB_DATABASE; $DB_HEALTH_QUERY;'" 2>&1)
  # Debugging: Print the output of the mariadb command
  # echo "Mariadb command output: $OUTPUT"

  # Check if the output contains an error
  if echo "$OUTPUT" | grep -qi "error"; then
    if echo "$OUTPUT" | grep -qi "doesn't exist"; then
      echo "Database not found."
    else
      echo "❌ Database error: $OUTPUT"
    fi

    CURRENT_USER_COUNT=0
  else
    # Extract the user count from the output
    CURRENT_USER_COUNT=$(echo "$OUTPUT" | grep -oP '\d+')
    # Debugging: Print the current user count
    echo "Current user count: $CURRENT_USER_COUNT"
  fi

  echo "Validate user count: $CURRENT_USER_COUNT"

  # Check if CURRENT_USER_COUNT is set and greater than 0
  if [ -n "$CURRENT_USER_COUNT" ] && [ "$CURRENT_USER_COUNT" -gt 0 ]; then
    echo "Database is online and contains $CURRENT_USER_COUNT users."
    TOTAL_USER_COUNT=$CURRENT_USER_COUNT
    break
  elif [ -n "$CURRENT_USER_COUNT" ] && [ "$CURRENT_USER_COUNT" -eq 0 ]; then
    echo "Database is online but contains no users."
    DATABASE_IS_ONLINE=1
    break
  else
    # Current user count is 0 or not set, wait longer...
    sleep $WAIT_TIME
  fi
done

echo "Validate total user count: $TOTAL_USER_COUNT"

if [ $TOTAL_USER_COUNT -eq 0 ]; then
  if [ $DATABASE_IS_ONLINE -eq 1 ]; then
    # Database does not contain any users (likley empty)
    # Restore from backup...
    echo "Restoring from backup: DB_INIT_FILE_LOCATION: $DB_INIT_FILE_LOCATION ..."

    sleep 10

    # Wait for the database backup deployment to be ready (DB_BACKUP_DEPLOYMENT_FULL_NAME)
    wait_for_rollout "$DB_BACKUP_DEPLOYMENT_FULL_NAME" "deployment"

    sleep 15

    # Restore the database from the latest backup
    restore_database_from_backup
  else
    echo "Database is offline."
  fi
else
  echo "Database appears to be healthy. No further action required."
fi
