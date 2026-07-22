# IntegraBr Core
Core Library for Integrabr all other libraries depend on.

## Usage
This library is a dependency for all other IntegraBr libraries as it contains RateLimit middleware and other code used by other libraries to work.

**Important:** If you have a custom API call that won't throw an exception for you can throw a TooManyRequests exception inside the job to emulate this behaviour.