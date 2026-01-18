destination=$1

date=`date --date="2 day ago" +%Y-%m-%d`
name="laravel-$date.log"
file="./storage/logs/$name"
gzip $file
zip_file="$file.gz"
scp $zip_file $destination && rm -rf $zip_file