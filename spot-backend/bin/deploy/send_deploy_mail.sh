email=$1
date=`date`
time=`date +%s`

source_folder="/root/amanpuri-api"
commit=`cd $source_folder && git log -n 1 | sed ':a;N;$!ba;s#\n#<br />#g;s#^<br />##g'`
healthchecks=`crontab -l | grep health | sed ':a;N;$!ba;s#\n#<br />#g;s#^<br />##g'`

subject="Amanpuri deployment $date ($time)"
content="All servers are updated to:<br/>$commit"
content="$content<br/><br/><br/>Current health-checks:<br/>$healthchecks"
cd $source_folder && php artisan email:send $email "$subject" "$content"

