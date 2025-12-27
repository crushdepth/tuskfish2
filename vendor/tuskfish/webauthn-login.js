/**
 * WebAuthn Login Handler
 *
 * Intercepts login form submission to detect WebAuthn 2FA requirement
 * and handles the authentication flow.
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  // Intercept login form submission to detect WebAuthn requirement
  // Find the form that has action ending with 'login/'
  const form = document.querySelector('form[action*="login/"]');

  if (!form) {
    return;
  }

  form.addEventListener('submit', async function(e) {
  e.preventDefault();

  const formElement = e.target;
  const formAction = formElement.getAttribute('action');
  const formData = new FormData(formElement);
  const statusDiv = document.getElementById('webauthn-status');

  try {
    // Submit login credentials
    const response = await fetch(formAction, {
      method: 'POST',
      body: formData
    });

    // Check if response is JSON (WebAuthn required) or HTML (normal login)
    const contentType = response.headers.get('content-type');

    if (contentType && contentType.includes('application/json')) {
      const data = await response.json();

      if (data.webauthn_required) {
        // Show WebAuthn prompt
        document.getElementById('webauthn-prompt').style.display = 'block';
        statusDiv.innerHTML = '<div class="alert alert-info">Requesting authentication...</div>';

        // Get authentication options
        const optionsResponse = await fetch(TFISH_URL + 'login/?action=authenticateOptions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(formData.get('token'))
        });

        if (!optionsResponse.ok) {
          throw new Error('Failed to get authentication options');
        }

        const options = await optionsResponse.json();

        // Convert base64 strings to ArrayBuffers
        options.publicKey.challenge = base64ToArrayBuffer(options.publicKey.challenge);
        options.publicKey.allowCredentials = options.publicKey.allowCredentials.map(cred => ({
          ...cred,
          id: base64ToArrayBuffer(cred.id)
        }));

        statusDiv.innerHTML = '<div class="alert alert-info">Please interact with your authenticator...</div>';

        // Get credential
        const credential = await navigator.credentials.get(options);

        statusDiv.innerHTML = '<div class="alert alert-info">Verifying...</div>';

        // Verify assertion
        const verifyResponse = await fetch(TFISH_URL + 'login/?action=authenticateVerify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            token: formData.get('token'),
            credentialId: arrayBufferToBase64(credential.rawId),
            clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
            authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
            signature: arrayBufferToBase64(credential.response.signature)
          })
        });

        const verifyData = await verifyResponse.json();

        if (verifyData.success) {
          statusDiv.innerHTML = '<div class="alert alert-success">Authentication successful! Redirecting...</div>';
          window.location.href = verifyData.redirect;
        } else {
          throw new Error(verifyData.error || 'Authentication failed');
        }
      }
    } else {
      // Normal HTML response - let browser handle redirect
      window.location.href = formAction;
    }
  } catch (error) {
    if (statusDiv) {
      const alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-danger';
      const strong = document.createElement('strong');
      strong.textContent = 'Error: ';
      alertDiv.appendChild(strong);
      alertDiv.appendChild(document.createTextNode(error.message));
      statusDiv.innerHTML = '';
      statusDiv.appendChild(alertDiv);
    } else {
      alert('Error: ' + error.message);
    }
  }
  });

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
