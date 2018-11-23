# Using the addRadio API

## General information

The base path of the addRadio API is:

    https://api.addradio.de/api

This call returns a JSON object describing the functions and parameters available in the respective context. This way, all functions should be self-explanatory and understandable for humans as well as for code generators and other automatic processing.

Let's take a look at "login", which describes itself as follows:

    "login": {
      "name": "login",
      "about": "Aquire a challenge for authentication",
      "params": [
        {
          "type": "string",
          "name": "name"
        }
      ],
      "method": [
        "GET",
        "POST"
      ],
      "return": "application/jwt"
    }

This function expects a parameter "name" of type String, is available for the HTTP methods GET and POST and returns an object of type "application/jwt", a [JSON Web Token].

[JSON Web Token]: https://tools.ietf.org/html/rfc7519  "JSON Web Token JWT"

## Authentication

Let's use the "login" function mentioned above to log-in into the system. The function expects a registered user name and returns a [JSON Web Token], which we will then use to perform the actual log-in process:

      https://api.addradio.de/api/login?name=customername

      {
        "Status: 403,
        "message": "Forbidden: customername",
        "info": "Unknown username: customername"
      }

We can see that even error messages are trying to provide an answer in clear text for humans, as well as a parsable status format for machines. If a function has been processed successfully, the API sends HTTP error code "200" (OK). In this case we get the error code "403", because the user account "customername" does not exist.

Assuming that we'll send a registered customer name instead, we'll receive a valid JWT:

      https://api.addradio.de/api/login?name=nacamar
    
      eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJuYWNhbWFyIiwic3V
      iIjoibG9naW4iLCJleHAiOjE0NzYzNzI5MjksImlhdCI6MTQ3NjM3Mjg2OSwibmF
      tZSI6Im5hY2FtYXIiLCJjaGFsbGVuZ2UiOiJtQXo2ZHlya1FWQ0NDcUJicVczNGd
      JWFpCU0JXWEZEWSJ9.zCVOx-en0VhXFqZdqbwm3DXuJA7ss27QtdW0zJkp3us

If we decode the data of such a JWT, we find:

      [iss] => addradio
      [sub] => login
      [exp] => xxxxxx2915
      [iat] => xxxxxx2855
      [name] => xxxcustomerxxx
      [challenge] => b61y7iuFse5DbqvJW2hUSEB5B7Yw2gBx

This response contains a [challenge] section with a randomly generated value. All we need to do is to copy this value into a response field, generate a new JWT with the updated payload, and - this is the most important part - encrypt it with our pre-assigned key. This key takes the role of a password and therefore must be kept secret.

We have to send our reply to the API before the time specified in the [exp] field of the JWT. That field indicates the time the token will expire, while [iat] indicates the time the original token has been issued. As you can see, the period of time a token remains valid is [exp] minus [iat], so 60 seconds in this case.

      [iss] => addradio
      [sub] => login
      [exp] => xxxxxx2915
      [iat] => xxxxxx2855
      [name] => xxxkundennamexxx
      [challenge] => b61y7iuFse5DbqvJW2hUSEB5B7Yw2gBx
      [response] => b61y7iuFse5DbqvJW2hUSEB5B7Yw2gBx

If the API can decode and validate our encrypted random challange, our identity is confirmed.

## Example in PHP

Please note that in our example implementation the [Firebase JWT library] is used, but any other implementation offering basic JWT functionality should also be ok for handling the token. Direct interaction with the JWT contents should only be necessary during the login phase though. After successfully authenticating, all you'll have to do is to write the JWT in the Authorization header of each request and to parse out an updated JWT from any API response containing an Authorization header, so that the token does not expire before the communication is completed. In this case, a new log-in into the API would be necessary.

[Firebase JWT library]: https://github.com/firebase/php-jwt  "Firebase JWT Library"

Having an Authorization header in the server response might be somewhat unusual, but it allows us to return both, the new token and the result of the API function, independently in the same response.
