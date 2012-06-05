#/bin/bash
curl -c /tmp/cookies.txt -b /tmp/cookies.txt https://master.zivios.biz/DeferredTransaction/runtransaction?transid=$1 --cacert /etc/ssl/certs/ZiviosCA.pem
