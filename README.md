oc-salesforce

Création d'une clef pour sales force : https://mannharleen.github.io/2020-03-03-salesforce-jwt/
$ openssl genrsa -out privatekey.pem 1024
$ openssl req -new -x509 -key privatekey.pem -out publickey.cer -days 3650