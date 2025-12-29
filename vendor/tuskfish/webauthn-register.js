/**
 * WebAuthn Registration Handler
 *
 * Handles registration of new WebAuthn credentials and credential revocation.
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  // Registration button handler
  const registerButton = document.getElementById('registerButton');
  if (registerButton) {
    registerButton.addEventListener('click', async function() {
      const statusDiv = document.getElementById('registerStatus');
      const credentialName = document.getElementById('credentialName').value;
      const token = document.getElementById('registerToken').value;

      try {
        statusDiv.innerHTML = '<div class="alert alert-info">Requesting registration options...</div>';

        // Get registration options from server
        const optionsResponse = await fetch(TFISH_URL + 'register/?action=registerOptions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(token)
        });

        // If session expired, server redirected to login - redirect the whole page
        if (optionsResponse.redirected && optionsResponse.url.includes('/login/')) {
          window.location.href = optionsResponse.url;
          return;
        }

        if (!optionsResponse.ok) {
          throw new Error('Failed to get registration options');
        }

        const options = await optionsResponse.json();

        // Convert base64 strings to ArrayBuffers
        options.publicKey.challenge = base64ToArrayBuffer(options.publicKey.challenge);
        options.publicKey.user.id = base64ToArrayBuffer(options.publicKey.user.id);

        // Convert excludeCredentials IDs to ArrayBuffers
        if (options.publicKey.excludeCredentials) {
          options.publicKey.excludeCredentials = options.publicKey.excludeCredentials.map(cred => ({
            ...cred,
            id: base64ToArrayBuffer(cred.id)
          }));
        }

        statusDiv.innerHTML = '<div class="alert alert-info">Please interact with your authenticator...</div>';

        // Create credential
        const credential = await navigator.credentials.create(options);

        statusDiv.innerHTML = '<div class="alert alert-info">Verifying credential...</div>';

        // Send credential to server for verification
        const verifyResponse = await fetch(TFISH_URL + 'register/?action=registerVerify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            token: token,
            credentialName: credentialName,
            clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
            attestationObject: arrayBufferToBase64(credential.response.attestationObject)
          })
        });

        if (!verifyResponse.ok) {
          throw new Error('Failed to verify credential');
        }

        statusDiv.innerHTML = '<div class="alert alert-success"><strong>Success!</strong> Credential registered. Reloading...</div>';
        setTimeout(() => location.reload(), 1500);

      } catch (error) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger';
        const strong = document.createElement('strong');
        strong.textContent = 'Error: ';
        alertDiv.appendChild(strong);
        alertDiv.appendChild(document.createTextNode(error.message));
        statusDiv.innerHTML = '';
        statusDiv.appendChild(alertDiv);
      }
    });
  }

  // Revoke credential confirmation handler
  document.querySelectorAll('.revoke-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      if (!confirm(TFISH_WEBAUTHN_REVOKE_CONFIRM)) {
        e.preventDefault();
      }
    });
  });

  // Helper functions
  function base64ToArrayBuffer(base64) {
    const binary = atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }
});
