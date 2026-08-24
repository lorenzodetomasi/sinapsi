<?php
// The Google My Business API template file
// @package WS
// @subpackage Localbiz_Theme
// @since WS 1.0
//https://developers.google.com/my-business/quickstarts/javascript
?>
<html>
  <head>
    <title> Google My Business API Demo </title>
    <meta charset='utf-8' />
  </head>
  <body>
    <h2> Google My Business API Web Demo </h2>

    This demonstration illustrates how to perform OAuth 2.0 authorization, sign in/out and call the
    Google My Business API using JavaScript and CORS. <p>
    The API Key and OAuth 2.0 Client ID values from Google APIs & Services must be added to the
    JavaScript source for this page. <p>

    <h3> OAuth2 Authorization and Sign Out </h3>

    Successful OAuth2 authorization grants this script access to the Google My Business API and
    enables you to view and manage business locations on Google. <p>
    A one-time acknowledgement is required to grant this script access to the Google account holding
    the API Key and OAuth 2.0 client ID credentials. <p>

    <div>
    <button id="authorize-button" style="display: none;"> Authorize </button>
    <button id="signout-button" style="display: none;"> Sign Out </button>
    </div> <p>

    You may verify current grant status and revoke active grants at any time using
    <a href="https://myaccount.google.com/permissions">My Account Permissions</a> (also available
    from <i>Settings</i> and <i>Sign-in & Security</i> when signed into your Google account). <p>

    <h3> API Requests </h3>

    <button id="accounts-button" style="display: none;"> Get Accounts </button>
    <button id="admins-button" style="display:none;"> Get Admins </button>
    <button id="locations-button" style="display:none;"> Get Locations </button>

    <h3> API Responses </h3>

    <div id="dynamic-content"> </div>

    <script type="text/javascript">

      // Enter an API key from the Google API Console:
      //   https://console.developers.google.com/apis/credentials
      var apiKey = '<?php echo GOOGLE_API_KEY; ?>';

      // Enter a client ID for a web application from the Google API Console:
      //   https://console.developers.google.com/apis/credentials?project=_
      // In your API Console project, add a JavaScript origin that corresponds
      // to the domain where you will be running the script.
      var clientId = '<?php echo OAUTH20_ID_CLIENT; ?>';

      // Use the latest Google My Business API version
      var gmb_api_version = 'https://mybusiness.googleapis.com/v4';

      // One or more authorization scopes. Additional scopes may be desired if
      // multiple APIs are used. Refer to the documentation for the API
      // or https://developers.google.com/people/v1/how-tos/authorizing
      // for details. At a minimum include the Google My Business authorization scope.
      var scopes = 'https://www.googleapis.com/auth/plus.business.manage';

      var authorizeButton = document.getElementById('authorize-button');
      var signoutButton = document.getElementById('signout-button');
      var accountsButton = document.getElementById('accounts-button');
      var adminsButton = document.getElementById('admins-button');
      var locationsButton = document.getElementById('locations-button');

      var accounts = [];

      function handleClientLoad() {
        // Load the API client and auth2 library
        gapi.load('client:auth2', initClient);
      }

      function initClient() {
        gapi.client.init({
            apiKey: apiKey,
            clientId: clientId,
            scope: scopes
        }).then(function () {
          // Listen for sign-in state changes.
          gapi.auth2.getAuthInstance().isSignedIn.listen(updateSigninStatus);

          // Handle the initial sign-in state.
          updateSigninStatus(gapi.auth2.getAuthInstance().isSignedIn.get());

          authorizeButton.onclick = handleAuthClick;
          signoutButton.onclick = handleSignoutClick;
          accountsButton.onclick = handleAccountsClick;
          adminsButton.onclick = handleAdminsClick;
          locationsButton.onclick = handleLocationsClick;
        });
      }

      function updateSigninStatus(isSignedIn) {
        if (isSignedIn) {
          authorizeButton.style.display = 'none';
          signoutButton.style.display = 'inline-block';
          accountsButton.style.display = 'inline-block';
        } else {
          authorizeButton.style.display = 'inline-block';
          signoutButton.style.display = 'none';
          accountsButton.style.display = 'none';
          adminsButton.style.display = 'none';
          locationsButton.style.display = 'none';
        }
      }

      function handleAuthClick(event) {
        gapi.auth2.getAuthInstance().signIn();
      }

      function handleSignoutClick(event) {
        gapi.auth2.getAuthInstance().signOut();
      }

      function handleAccountsClick(event) {
        let user = gapi.auth2.getAuthInstance().currentUser.get();
        let oauthToken = user.getAuthResponse().access_token;
        let req = gmb_api_version + '/accounts';
        let xhr = new XMLHttpRequest();
        let p = document.createElement('p');

        p.appendChild(document.createTextNode('Accounts'));

        console.log(req);

        xhr.responseType = 'json';
        xhr.open('GET', req);
        xhr.setRequestHeader('Authorization', 'Bearer ' + oauthToken);

        xhr.onload = function() {

          if (xhr.status != 200) {
            return;
          }

          for (let i = 0; i < xhr.response.accounts.length; i++) {

            let account = xhr.response.accounts[i].name;

            if (accounts.indexOf(account) === -1) {
              accounts.push(account);
            }

            p.appendChild(document.createElement('br'));
            p.appendChild(document.createTextNode(account));
            p.appendChild(document.createTextNode(' accountName: ' + xhr.response.accounts[i].accountName));
            p.appendChild(document.createTextNode(' type: ' + xhr.response.accounts[i].type));
            p.appendChild(document.createTextNode(' role: ' + xhr.response.accounts[i].role));
            p.appendChild(document.createTextNode(' state.status: ' + xhr.response.accounts[i].state.status));

            adminsButton.style.display = 'inline-block';
            locationsButton.style.display = 'inline-block';
          }
        };
        xhr.send();
        document.getElementById('dynamic-content').appendChild(p);
      }

      function handleAdminsClick(event) {
        let p = document.createElement('p');

        p.appendChild(document.createTextNode('Admins'));

        for (let i = 0; i < accounts.length; i++) {

          let user = gapi.auth2.getAuthInstance().currentUser.get();
          let oauthToken = user.getAuthResponse().access_token;
          let xhr = new XMLHttpRequest();
          let req = gmb_api_version + '/' + accounts[i] + '/admins';

          console.log(req);

          xhr.responseType = 'json';
          xhr.open('GET', req);
          xhr.setRequestHeader('Authorization', 'Bearer ' + oauthToken);

          xhr.onload = function() {

            if (xhr.status != 200) {
              return;
            }

            for (let j = 0; j < xhr.response.admins.length; j++) {

              p.appendChild(document.createElement('br'));
              p.appendChild(document.createTextNode(xhr.response.admins[j].name));
              p.appendChild(document.createTextNode(' adminName: ' + xhr.response.admins[j].adminName));
              p.appendChild(document.createTextNode(' role: ' + xhr.response.admins[j].role));
            }
          };
          xhr.send();
        }
        document.getElementById('dynamic-content').appendChild(p);
      }

      function handleLocationsClick(event) {
        let p = document.createElement('p');

        p.appendChild(document.createTextNode('Locations'));

        for (let i = 0; i < accounts.length; i++) {

          let user = gapi.auth2.getAuthInstance().currentUser.get();
          let oauthToken = user.getAuthResponse().access_token;
          let xhr = new XMLHttpRequest();
          let req = gmb_api_version + '/' + accounts[i] + '/locations';

          xhr.responseType = 'json';
          xhr.open('GET', req);
          xhr.setRequestHeader('Authorization', 'Bearer ' + oauthToken);

          xhr.onload = function() {

            if (xhr.status != 200 || xhr.response.locations == undefined) {
              return;
            }

            for (let j = 0; j < xhr.response.locations.length; j++) {
              console.log(xhr.response.locations[j]);
              p.appendChild(document.createElement('br'));
              p.appendChild(document.createTextNode(xhr.response.locations[j].name));
              p.appendChild(document.createTextNode(' locationName: ' + xhr.response.locations[j].locationName));
              p.appendChild(document.createTextNode(' address.addressLines: ' + xhr.response.locations[j].address.addressLines));
              p.appendChild(document.createTextNode(' locationState isVerified: ' + xhr.response.locations[j].locationState.isVerified));
            }
          };
          xhr.send();
        }
        document.getElementById('dynamic-content').appendChild(p);
      }

    </script>

    <script async defer src="https://apis.google.com/js/api.js"
      onload="this.onload=function(){};handleClientLoad()"
      onreadystatechange="if (this.readyState === 'complete') this.onload()">
    </script>

  </body>
</html>
