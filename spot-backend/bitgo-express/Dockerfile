FROM node:6-alpine

USER root

RUN apk add --update --no-cache bash python git make g++ && git clone https://github.com/BitGo/BitGoJS && cd /BitGoJS && npm install

WORKDIR /BitGoJS

EXPOSE 3080

CMD ["./bin/bitgo-express", "--bind", "0.0.0.0"]