test -n "$BRANCH"
test -n "$BUILD_NAMESPACE"
echo "BUILIDING $DEPLOYMENT_NAME:$BRANCH"
  oc -n $BUILD_NAMESPACE process -f ./openshift/docker-build.yml \
    -p NAME=$DEPLOYMENT_NAME \
    -p BUILD_TAG=$BRANCH \
    -p DOCKER_FROM_IMAGE=$DOCKER_FROM_IMAGE \
    -p IMAGE_REPO=$IMAGE_REPO \
    -p IMAGE_NAME=$DEPLOYMENT_NAME \
    -p IMAGE_TAG=$BUILD_NAMESPACE \
    -p SOURCE_REPOSITORY_URL=$SOURCE_REPOSITORY_URL \
    -p DOCKER_FILE_PATH=$DOCKER_FILE_PATH \
    -p DB_POD_NAME: $DB_POD_NAME \
    -p DATABASE_NAME: $DATABASE_NAME \
    -p DB_USER: $DB_USER \
    -p DB_PASSWORD: $DB_PASSWORD \
    -p PHP_INI_ENVIRONMENT=$PHP_INI_ENVIRONMENT \
    -p APP_BRANCH_VERSION=$APP_BRANCH_VERSION \
    -p F2F_BRANCH_VERSION=$F2F_BRANCH_VERSION \
    -p HVP_BRANCH_VERSION=$HVP_BRANCH_VERSION \
    -p FORMAT_BRANCH_VERSION=$FORMAT_BRANCH_VERSION \
    -p CERTIFICATE_BRANCH_VERSION=$CERTIFICATE_BRANCH_VERSION \
    -p CUSTOMCERT_BRANCH_VERSION=$CUSTOMCERT_BRANCH_VERSION \
    -p DATAFLOWS_BRANCH_VERSION=$DATAFLOWS_BRANCH_VERSION \
    -p BACKUP_IMAGE=$BACKUP_IMAGE \
    -p SOURCE_CONTEXT_DIR=$SOURCE_CONTEXT_DIR | oc -n $BUILD_NAMESPACE apply -f -
oc -n $BUILD_NAMESPACE start-build bc/$DEPLOYMENT_NAME --commit=$BRANCH --no-cache --wait
