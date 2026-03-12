/**
 * WebAuthn Registration Handler
 *
 * Handles registration of new WebAuthn credentials and credential revocation.
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  'use strict';
  // Validate TFISH_URL is properly configured
  if (!validateTfishUrl()) {
    console.error('TFISH_URL is not properly configured');
    return;
  }

  // Rate limiting state
  let registrationInProgress = false;
  let lastRegistrationAttempt = 0;
  const RATE_LIMIT_MS = 2000; // Minimum 2 seconds between attempts

  // Registration button handler
  const registerButton = document.getElementById('registerButton');
  if (registerButton) {
    registerButton.addEventListener('click', async function() {
      // Get and validate DOM elements
      const statusDiv = document.getElementById('registerStatus');
      const credentialNameInput = document.getElementById('credentialName');
      const tokenInput = document.getElementById('registerToken');

      if (!statusDiv || !credentialNameInput || !tokenInput) {
        console.error('Required DOM elements not found');
        alert('Page error: Required elements missing. Please reload the page.');
        return;
      }

      const credentialName = credentialNameInput.value;
      const token = tokenInput.value;

      // Validate inputs
      if (!validateCredentialName(credentialName)) {
        displayError(statusDiv, 'Credential name must be 255 characters or less and contain only letters, numbers, spaces, and basic punctuation.');
        return;
      }

      if (!validateToken(token)) {
        displayError(statusDiv, 'Invalid security token. Please reload the page.');
        return;
      }

      // Rate limiting check
      const now = Date.now();
      if (registrationInProgress) {
        displayError(statusDiv, 'Registration already in progress. Please wait.');
        return;
      }

      if (now - lastRegistrationAttempt < RATE_LIMIT_MS) {
        displayError(statusDiv, 'Please wait a moment before trying again.');
        return;
      }

      lastRegistrationAttempt = now;
      registrationInProgress = true;

      try {
        statusDiv.innerHTML = '<div class="alert alert-info">Requesting registration options...</div>';

        // Get registration options from server with timeout
        const optionsController = new AbortController();
        const optionsTimeoutId = setTimeout(() => optionsController.abort(), 30000); // 30 second timeout

        const optionsResponse = await fetch(TFISH_URL + 'register/?action=registerOptions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(token),
          signal: optionsController.signal
        });

        clearTimeout(optionsTimeoutId);

        // If session expired, server redirected to login
        if (optionsResponse.redirected) {
          // Don't use server-provided redirect URL - use our known-safe login URL
          // This prevents open redirect vulnerabilities
          // Ensure TFISH_URL has trailing slash for proper URL construction
          const baseUrl = TFISH_URL.endsWith('/') ? TFISH_URL : TFISH_URL + '/';
          window.location.href = baseUrl + 'login/';
          return;
        }

        if (!optionsResponse.ok) {
          throw new Error('Failed to get registration options (server error)');
        }

        const options = await optionsResponse.json();

        // Validate response schema
        if (!validateRegistrationOptions(options)) {
          throw new Error('Invalid registration options received from server');
        }

        // Convert base64 strings to ArrayBuffers with error handling
        try {
          options.publicKey.challenge = base64ToArrayBuffer(options.publicKey.challenge);
          options.publicKey.user.id = base64ToArrayBuffer(options.publicKey.user.id);

          // Convert excludeCredentials IDs to ArrayBuffers
          if (options.publicKey.excludeCredentials) {
            options.publicKey.excludeCredentials = options.publicKey.excludeCredentials.map(cred => ({
              ...cred,
              id: base64ToArrayBuffer(cred.id)
            }));
          }
        } catch (decodeError) {
          throw new Error('Failed to decode server response');
        }

        statusDiv.innerHTML = '<div class="alert alert-info">Please interact with your authenticator...</div>';

        // Create credential
        const credential = await navigator.credentials.create(options);

        // Validate credential structure
        if (!credential || !credential.response || !credential.rawId) {
          throw new Error('No credential received from authenticator');
        }

        if (!credential.response.clientDataJSON || !credential.response.attestationObject) {
          throw new Error('No credential received from authenticator');
        }

        statusDiv.innerHTML = '<div class="alert alert-info">Verifying credential...</div>';

        // Send credential to server for verification with timeout
        const verifyController = new AbortController();
        const verifyTimeoutId = setTimeout(() => verifyController.abort(), 30000); // 30 second timeout

        const verifyResponse = await fetch(TFISH_URL + 'register/?action=registerVerify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            token: token,
            credentialName: credentialName,
            credentialId: arrayBufferToBase64(credential.rawId),
            clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
            attestationObject: arrayBufferToBase64(credential.response.attestationObject)
          }),
          signal: verifyController.signal
        });

        clearTimeout(verifyTimeoutId);

        if (!verifyResponse.ok) {
          throw new Error('Credential verification failed (server error)');
        }

        const verifyResult = await verifyResponse.json();

        if (!verifyResult || verifyResult.success !== true) {
          // Don't throw server-provided error messages - prevent information disclosure
          throw new Error('Credential verification failed');
        }

        statusDiv.innerHTML = '<div class="alert alert-success"><strong>Success!</strong> Credential registered. Reloading...</div>';
        // Ensure TFISH_URL has trailing slash for proper URL construction
        const baseUrl = TFISH_URL.endsWith('/') ? TFISH_URL : TFISH_URL + '/';
        setTimeout(() => window.location.href = baseUrl + 'register/', 1000);

      } catch (error) {
        // Sanitize error message - only show safe, pre-defined messages
        // Use exact matching to prevent information disclosure via crafted server responses
        let safeMessage = 'Registration failed. Please try again.';

        // WebAuthn API errors (safe to display as-is)
        if (error.name === 'NotAllowedError') {
          safeMessage = 'Registration was cancelled or timed out.';
        } else if (error.name === 'InvalidStateError') {
          safeMessage = 'This authenticator is already registered.';
        } else if (error.name === 'AbortError') {
          safeMessage = 'Request timed out. Please try again.';
        } else if (error.message && typeof error.message === 'string') {
          // CRITICAL: Use exact string matching to prevent info disclosure
          // Only our own pre-defined error messages are allowed
          switch (error.message) {
            case 'Page error: Required elements missing. Please reload the page.':
            case 'Credential name must be 255 characters or less and contain only letters, numbers, spaces, and basic punctuation.':
            case 'Invalid security token. Please reload the page.':
            case 'Registration already in progress. Please wait.':
            case 'Please wait a moment before trying again.':
            case 'Failed to get registration options (server error)':
            case 'Invalid registration options received from server':
            case 'Failed to decode server response':
            case 'No credential received from authenticator':
            case 'Credential verification failed (server error)':
            case 'Credential verification failed':
              safeMessage = error.message;
              break;
            default:
              // Any other error message (including server-supplied) is NOT displayed
              // This prevents leaking database paths, internal errors, etc.
              safeMessage = 'Registration failed. Please try again.';
          }
        }

        displayError(statusDiv, safeMessage);
        console.error('Registration error:', error);
      } finally {
        registrationInProgress = false;
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

  // Validation helper functions
  function validateTfishUrl() {
    if (typeof TFISH_URL !== 'string' || !TFISH_URL) {
      return false;
    }

    try {
      const url = new URL(TFISH_URL);
      // Must be http or https
      if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
      }
      // Must have a valid hostname
      if (!url.hostname || url.hostname.length === 0) {
        return false;
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function validateCredentialName(name) {
    if (typeof name !== 'string') {
      return false;
    }
    // Must be 0-255 characters (empty is allowed - will display as "Unnamed")
    if (name.length > 255) {
      return false;
    }
    // If not empty, only allow alphanumeric, spaces, and safe punctuation
    if (name.length > 0) {
      const safePattern = /^[a-zA-Z0-9\s\-_.,'()]+$/;
      return safePattern.test(name);
    }
    return true;
  }

  function validateToken(token) {
    if (typeof token !== 'string') {
      return false;
    }
    // Token should be non-empty and reasonable length (CSRF tokens are typically 32-128 chars)
    if (token.length < 10 || token.length > 256) {
      return false;
    }
    // Allow common token formats:
    // - Hex: [a-f0-9]
    // - Base64: [A-Za-z0-9+/=]
    // - Base64URL: [A-Za-z0-9\-_=]
    // - UUID: includes hyphens
    // But exclude control characters, spaces, and special chars that might indicate injection
    const tokenPattern = /^[a-zA-Z0-9+/=_\-]+$/;
    return tokenPattern.test(token);
  }

  function validateRegistrationOptions(options) {
    if (!options || typeof options !== 'object') {
      return false;
    }

    if (!options.publicKey || typeof options.publicKey !== 'object') {
      return false;
    }

    const pk = options.publicKey;

    // Maximum lengths to prevent memory exhaustion attacks
    const MAX_CHALLENGE_LENGTH = 512;      // Base64 challenge should be ~64-128 chars
    const MAX_STRING_LENGTH = 1024;        // Reasonable max for names/strings
    const MAX_USER_ID_LENGTH = 256;        // User ID should be modest
    const MAX_CREDENTIALS_COUNT = 100;     // Reasonable max excluded credentials

    // Validate challenge field
    if (typeof pk.challenge !== 'string' || pk.challenge.length === 0) {
      return false;
    }
    if (pk.challenge.length > MAX_CHALLENGE_LENGTH) {
      return false;
    }

    // Validate relying party
    if (!pk.rp || typeof pk.rp !== 'object' || typeof pk.rp.name !== 'string') {
      return false;
    }
    if (pk.rp.name.length > MAX_STRING_LENGTH) {
      return false;
    }
    // Validate rp.id if present (optional field)
    if (pk.rp.id !== undefined && (typeof pk.rp.id !== 'string' || pk.rp.id.length > MAX_STRING_LENGTH)) {
      return false;
    }

    // Validate user object
    if (!pk.user || typeof pk.user !== 'object') {
      return false;
    }

    if (typeof pk.user.id !== 'string' || pk.user.id.length === 0) {
      return false;
    }
    if (pk.user.id.length > MAX_USER_ID_LENGTH) {
      return false;
    }

    if (typeof pk.user.name !== 'string' || pk.user.name.length === 0) {
      return false;
    }
    if (pk.user.name.length > MAX_STRING_LENGTH) {
      return false;
    }

    if (typeof pk.user.displayName !== 'string' || pk.user.displayName.length === 0) {
      return false;
    }
    if (pk.user.displayName.length > MAX_STRING_LENGTH) {
      return false;
    }

    // Validate pubKeyCredParams
    if (!Array.isArray(pk.pubKeyCredParams) || pk.pubKeyCredParams.length === 0) {
      return false;
    }
    if (pk.pubKeyCredParams.length > 50) { // Reasonable max algorithm count
      return false;
    }

    // Validate excludeCredentials if present
    if (pk.excludeCredentials !== undefined && pk.excludeCredentials !== null) {
      if (!Array.isArray(pk.excludeCredentials)) {
        return false;
      }
      if (pk.excludeCredentials.length > MAX_CREDENTIALS_COUNT) {
        return false;
      }
      // Each excluded credential must have an id with reasonable length
      for (const cred of pk.excludeCredentials) {
        if (!cred || typeof cred.id !== 'string') {
          return false;
        }
        if (cred.id.length > MAX_CHALLENGE_LENGTH) {
          return false;
        }
      }
    }

    return true;
  }

  function displayError(statusDiv, message) {
    if (!statusDiv) {
      console.error('Cannot display error: statusDiv not available');
      return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger';
    const strong = document.createElement('strong');
    strong.textContent = 'Error: ';
    alertDiv.appendChild(strong);
    // Use createTextNode to prevent XSS
    alertDiv.appendChild(document.createTextNode(message));
    statusDiv.innerHTML = '';
    statusDiv.appendChild(alertDiv);
  }

  // Base64 conversion helper functions
  function base64ToArrayBuffer(base64) {
    if (typeof base64 !== 'string' || base64.length === 0) {
      throw new Error('Invalid base64 input');
    }

    // Maximum length to prevent memory exhaustion
    // WebAuthn credentials are typically < 1KB, extreme cases < 50KB
    // Allow 100KB with safety buffer (100x typical size, 2x worst case)
    const MAX_BASE64_LENGTH = 140000; // ~100KB when decoded
    if (base64.length > MAX_BASE64_LENGTH) {
      throw new Error('Base64 input too large');
    }

    try {
      // Convert base64url to base64
      let standardBase64 = base64.replace(/-/g, '+').replace(/_/g, '/');

      // Add padding if needed (base64url often omits padding)
      while (standardBase64.length % 4 !== 0) {
        standardBase64 += '=';
      }

      const binary = atob(standardBase64);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
      }
      return bytes.buffer;
    } catch (e) {
      throw new Error('Base64 decode failed');
    }
  }

  function arrayBufferToBase64(buffer) {
    if (!buffer || !(buffer instanceof ArrayBuffer)) {
      throw new Error('Invalid ArrayBuffer input');
    }

    // Maximum size to prevent memory exhaustion
    // WebAuthn credentials are typically < 1KB, extreme cases < 50KB
    // Allow 100KB with safety buffer (100x typical size, 2x worst case)
    const MAX_BUFFER_SIZE = 102400; // 100KB
    if (buffer.byteLength > MAX_BUFFER_SIZE) {
      throw new Error('ArrayBuffer too large');
    }

    try {
      const bytes = new Uint8Array(buffer);
      // Use Array.from + map for O(n) performance instead of string concatenation O(n²)
      const binary = Array.from(bytes, byte => String.fromCharCode(byte)).join('');
      return btoa(binary);
    } catch (e) {
      throw new Error('Base64 encode failed');
    }
  }
});
