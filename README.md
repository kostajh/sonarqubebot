# sonarqubebot

Receive SonarQube webhook data and post reviews to Gerrit.

This project is hosted at https://tools.wmflabs.org/sonarqubebot/ where it listens for [webhook data from SonarQube](https://sonarcloud.io/documentation/project-administration/webhooks/). It then posts a review to Gerrit based on the quality gate success/failure.

A future version will post inline comments with information about the violations detected by SonarQube.

## Configuration

Set this in `.env.local`

- `GERRIT_USERNAME` - the username to post under
- `GERRIT_HTTP_PASSWORD` - the HTTP password for that user (not the same as their login password)
- `SONARQUBE_HMAC` - the secret set in the SonarQube webhook UI, used for generating the HMAC
