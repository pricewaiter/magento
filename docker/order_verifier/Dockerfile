# Docker image that provides a simple HTTP app that emulates the PriceWaiter order validator
FROM node:5

ENV PORT 80
EXPOSE 80

WORKDIR /usr/src

COPY package.json ./
RUN npm install

COPY . ./

CMD npm start
