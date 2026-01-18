echo -e "\033[32;7mThese files will be updated:\e[0m";
./deploy.sh web1 0
echo -e "\033[31;7mAre you sure you want continue?(y|n)\e[0m";
read answer
if echo "$answer" | grep -iq "^y" ;then
    echo "Deploying..."
else
    echo Abort
    exit
fi

./deploy_without_confirm.sh
