#!/bin/bash

crontab /etc/cron.d/${SERVICE_NAME}
cron -f