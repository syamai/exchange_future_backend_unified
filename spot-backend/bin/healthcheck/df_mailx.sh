email=$1

hostname=`hostname`
percent=`df -h / --output=pcent | tail -n 1`
percent=${percent%?}
if [[ $percent -ge 80 ]]; then
    subject="WARNING: $hostname low disk space: $percent%"
    content="WARNING: $hostname low disk space: $percent%"
    echo "$content" | mailx -s "$subject" $email
fi