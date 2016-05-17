# Docker image that runs functional tests (written in JS) against a running Magento instance.
FROM node:5

COPY ./docker/functional-tests/bin/* /usr/local/bin/

WORKDIR /usr/src

COPY ./tests/functional/package.json ./
RUN npm install

COPY ./tests/functional ./

CMD ["run-codeship-tests"]
