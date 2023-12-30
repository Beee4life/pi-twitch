# Twitch plugin

## Setup

Add this to `.env`

```
TW_API_URL='https://api.igdb.com/v4'
TW_CLIENT_ID='{client_id}'
TW_CLIENT_SECRET='{client_secret}'
TW_TOKEN_URL='https://id.twitch.tv/oauth2/token' 
```

## Customize this first
The plugin works but needs some customization in terms of post types to your situation. So don't start by starting a cron right away :) 

For this I went with the assumption your post type would be `game`, but you can change this in the file `constants.php`. There's a constant which defines your games post type.

## Raw output
`PiTwitch.php` - line 34 has a test function. Uncomment it and you get a dump from a raw api call. It just pulls data, it doesn't map it yet, the dump is before the mapping.
