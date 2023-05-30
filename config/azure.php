<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

// Access environment through the config helper
// This will avoid issues when using Laravel's config caching
// https://laravel.com/docs/8.x/configuration#configuration-caching
return [
  'appId'             => env('OUTLOOK_APP_ID', ''),
  'appSecret'         => env('OUTLOOK_APP_SECRET', ''),
  'redirectUri'       => env('OUTLOOK_REDIRECT_URI', ''),
  'scopes'            => env('OUTLOOK_SCOPES', ''),
  'authority'         => env('OUTLOOK_AUTHORITY', 'https://login.microsoftonline.com/common'),
  'authorizeEndpoint' => env('OUTLOOK_AUTHORIZE_ENDPOINT', '/oauth2/v2.0/authorize'),
  'tokenEndpoint'     => env('OUTLOOK_TOKEN_ENDPOINT', '/oauth2/v2.0/token'),
];
