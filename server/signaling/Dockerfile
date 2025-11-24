FROM node:18-alpine

WORKDIR /usr/src/app

# Install dependencies (package.json should be present in the folder)
COPY package*.json ./
RUN npm install --production

# Copy app sources
COPY . .

EXPOSE 4000

CMD ["node", "index.js"]
