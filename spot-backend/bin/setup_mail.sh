password="$1"

if [ -z $password ]; then
    echo "Please enter password"
    read -s password
fi
if [ -z $password ]; then
    echo "Invalid password"
exit
fi

yum install mailx -y

mkdir ~/.certs
certutil -N -d ~/.certs
echo -n | openssl s_client -connect smtp.gmail.com:465 | sed -ne '/-BEGIN CERTIFICATE-/,/-END CERTIFICATE-/p' > ~/.certs/gmail.crt
certutil -A -n "Google Internet Authority" -t "C,," -d ~/.certs -i ~/.certs/gmail.crt

echo "set smtp-use-starttls" >> /etc/mail.rc
echo "set ssl-verify=ignore" >> /etc/mail.rc
echo "set smtp-auth=login" >> /etc/mail.rc
echo "set smtp=smtp://smtp.gmail.com:587" >> /etc/mail.rc
echo 'set from="contact@vcc.exchange(VCC)"' >> /etc/mail.rc
echo "set smtp-auth-user=contact@vcc.exchange" >> /etc/mail.rc
echo "set smtp-auth-password=$password" >> /etc/mail.rc
echo "set ssl-verify=ignore" >> /etc/mail.rc
echo "set nss-config-dir=/root/.certs" >> /etc/mail.rc