# Auto.Vote

Auto.Vote is a web application that allows users to automatically upvote, downvote, and curate content on the Hive blockchain.

## Features

- Hive Login using Keychain for account access
- Follow other users to auto upvote their posts, comments or downvote
- Curation trail - Build a custom trail that auto upvotes content
- Set vote weight and vote scaling preferences per user
- Pause auto-voting if resource credits are low
- User discovery page to easily find new users to follow
- User profile pages show followers and their configurations
- Auto-claim pending rewards to simplify reward collection

## Usage

1. Install [Hive Keychain](https://chrome.google.com/webstore/detail/hive-keychain/jcacnejopjdphbnjgfaaobbfafkihpep) and create an account
2. Visit [auto.vote](https://auto.vote) and login using Hive Keychain
3. Follow users or curation trails you wish you automatically vote on
4. Customize weight and scaling preferences per user
5. Check your profile page to see current followers and settings
6. Use auto-claiming or manual reward redemption options

## Running Locally

Prerequisites:
- Node.js
- MySQL

```
# Install backend dependencies
cd backend
npm install

# Configure your env variables
cp .env.example .env

# Run database migrations
npx sequelize-cli db:migrate

# Start backend in development
npm run dev

# Install frontend dependencies
cd frontend
npm install

# Start the React frontend
npm start
```

The application will now be running on `http://localhost:3000`

## Tech Stack

**Frontend:** React, Redux, Bootstrap

**Backend:** Laravel, MySQL
