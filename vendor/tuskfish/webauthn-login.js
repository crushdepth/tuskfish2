/**
 * WebAuthn Login Handler
 *
 * Intercepts login form submission to detect WebAuthn 2FA requirement
 * and handles the authentication flow.
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
  let authenticationInProgress = false;
  let lastAuthenticationAttempt = 0;
  const RATE_LIMIT_MS = 2000; // Minimum 2 seconds between attempts

  /**
   * Validates that a URL is safe for redirect (prevents open redirect attacks)
   * Only allows relative URLs or same-origin URLs
   */
  function isSafeRedirect(url) {
    if (!url) return false;

    try {
      // Allow relative URLs (start with /)
      if (url.startsWith('/')) {
        // Prevent protocol-relative URLs (//evil.com)
        if (url.startsWith('//')) {
          return false;
        }
        return true;
      }

      // For absolute URLs, check they're same origin
      const urlObj = new URL(url);
      const currentOrigin = window.location.origin;

      return urlObj.origin === currentOrigin;
    } catch (e) {
      // Invalid URL
      return false;
    }
  }

  // Intercept login form submission to detect WebAuthn requirement
  // Find the form that has action ending with 'login/' (exact match at end)
  const form = document.querySelector('form[action$="login/"]');

  if (!form) {
    return;
  }

  form.addEventListener('submit', async function(e) {
  e.preventDefault();

  const formElement = e.target;
  const formAction = formElement.getAttribute('action');
  const formData = new FormData(formElement);
  const statusDiv = document.getElementById('webauthn-status');

  // Validate form action is safe before sending credentials
  if (!formAction || !isSafeRedirect(formAction)) {
    displayError(statusDiv, 'Invalid form configuration. Please reload the page.');
    return;
  }

  // Rate limiting check
  const now = Date.now();
  if (authenticationInProgress) {
    displayError(statusDiv, 'Authentication already in progress. Please wait.');
    return;
  }

  if (now - lastAuthenticationAttempt < RATE_LIMIT_MS) {
    displayError(statusDiv, 'Please wait a moment before trying again.');
    return;
  }

  // Validate token exists
  const token = formData.get('token');
  if (!validateToken(token)) {
    displayError(statusDiv, 'Invalid security token. Please reload the page.');
    return;
  }

  lastAuthenticationAttempt = now;
  authenticationInProgress = true;

  try {
    // Submit login credentials with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout

    const response = await fetch(formAction, {
      method: 'POST',
      body: formData,
      redirect: 'follow',
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    // Check if server redirected us (password-only login succeeded)
    if (response.redirected) {
      // Password auth succeeded, server sent Location header
      // Validate redirect URL before following it
      if (isSafeRedirect(response.url)) {
        window.location.href = response.url;
      } else {
        throw new Error('Unsafe redirect detected');
      }
      return;
    }

    // Check if response is JSON (WebAuthn required)
    const contentType = response.headers.get('content-type');

    if (contentType && contentType.startsWith('application/json')) {
      const data = await response.json();

      if (data.webauthn_required) {
        // Show WebAuthn prompt
        const webauthnPrompt = document.getElementById('webauthn-prompt');
        if (webauthnPrompt) {
          webauthnPrompt.style.display = 'block';
        }

        if (statusDiv) {
          statusDiv.innerHTML = '<div class="alert alert-info">Requesting authentication...</div>';
        }

        // Get authentication options with timeout
        const optionsController = new AbortController();
        const optionsTimeoutId = setTimeout(() => optionsController.abort(), 30000);

        const optionsResponse = await fetch(TFISH_URL + 'login/?action=authenticateOptions', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(token),
          signal: optionsController.signal
        });

        clearTimeout(optionsTimeoutId);

        if (!optionsResponse.ok) {
          throw new Error('Failed to get authentication options');
        }

        const options = await optionsResponse.json();

        // Validate response schema
        if (!validateAuthenticationOptions(options)) {
          throw new Error('Invalid authentication options received from server');
        }

        // Convert base64 strings to ArrayBuffers with error handling
        try {
          options.publicKey.challenge = base64ToArrayBuffer(options.publicKey.challenge);

          // Convert allowCredentials if present (optional for discoverable credentials)
          if (options.publicKey.allowCredentials && Array.isArray(options.publicKey.allowCredentials)) {
            options.publicKey.allowCredentials = options.publicKey.allowCredentials.map(cred => ({
              ...cred,
              id: base64ToArrayBuffer(cred.id)
            }));
          }
        } catch (decodeError) {
          throw new Error('Failed to decode server response');
        }

        if (statusDiv) {
          statusDiv.innerHTML = '<div class="alert alert-info">Please interact with your authenticator...</div>';
        }

        // Get credential
        const credential = await navigator.credentials.get(options);

        // Validate credential structure
        if (!credential || !credential.response || !credential.rawId) {
          throw new Error('No credential received from authenticator');
        }

        if (!credential.response.clientDataJSON || !credential.response.authenticatorData || !credential.response.signature) {
          throw new Error('No credential received from authenticator');
        }

        if (statusDiv) {
          statusDiv.innerHTML = '<div class="alert alert-info">Verifying...</div>';
        }

        // Verify assertion with timeout
        const verifyController = new AbortController();
        const verifyTimeoutId = setTimeout(() => verifyController.abort(), 30000);

        const verifyResponse = await fetch(TFISH_URL + 'login/?action=authenticateVerify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            token: token,
            credentialId: arrayBufferToBase64(credential.rawId),
            clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
            authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
            signature: arrayBufferToBase64(credential.response.signature)
          }),
          signal: verifyController.signal
        });

        clearTimeout(verifyTimeoutId);

        if (!verifyResponse.ok) {
          throw new Error('Authentication failed');
        }

        const verifyData = await verifyResponse.json();

        if (verifyData.success === true) {
          // Validate redirect URL before following it
          // Ensure TFISH_URL has trailing slash for proper URL construction
          const baseUrl = TFISH_URL.endsWith('/') ? TFISH_URL : TFISH_URL + '/';
          const redirectUrl = verifyData.redirect || (baseUrl + 'admin/');
          if (isSafeRedirect(redirectUrl)) {
            if (statusDiv) {
              statusDiv.innerHTML = '<div class="alert alert-success">Authentication successful! Redirecting...</div>';
            }
            window.location.href = redirectUrl;
          } else {
            throw new Error('Unsafe redirect detected');
          }
        } else {
          // Don't display server-provided error messages - prevent information disclosure
          throw new Error('Authentication failed');
        }
      }
    } else {
      // Login failed or other error - reload page to show error
      // The server will display the login form with error message
      window.location.reload();
    }
  } catch (error) {
    // Sanitize error message - only show safe, pre-defined messages
    // Use exact matching to prevent information disclosure via crafted server responses
    let safeMessage = 'Login failed. Please try again.';

    // WebAuthn API errors (safe to display as-is)
    if (error.name === 'NotAllowedError') {
      safeMessage = 'Authentication was cancelled or timed out.';
    } else if (error.name === 'InvalidStateError') {
      safeMessage = 'Authentication error. Please try again.';
    } else if (error.name === 'AbortError') {
      safeMessage = 'Request timed out. Please try again.';
    } else if (error.message && typeof error.message === 'string') {
      // CRITICAL: Use exact string matching to prevent info disclosure
      // Only our own pre-defined error messages are allowed
      switch (error.message) {
        case 'Invalid form configuration. Please reload the page.':
        case 'Authentication already in progress. Please wait.':
        case 'Please wait a moment before trying again.':
        case 'Invalid security token. Please reload the page.':
        case 'Unsafe redirect detected':
        case 'Failed to get authentication options':
        case 'Invalid authentication options received from server':
        case 'Failed to decode server response':
        case 'No credential received from authenticator':
        case 'Authentication failed':
          safeMessage = error.message;
          break;
        default:
          // Any other error message (including server-supplied) is NOT displayed
          // This prevents leaking database paths, internal errors, etc.
          safeMessage = 'Login failed. Please try again.';
      }
    }

    displayError(statusDiv, safeMessage);
  } finally {
    authenticationInProgress = false;
  }
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

  function validateAuthenticationOptions(options) {
    if (!options || typeof options !== 'object') {
      return false;
    }

    if (!options.publicKey || typeof options.publicKey !== 'object') {
      return false;
    }

    const pk = options.publicKey;

    // Maximum lengths to prevent memory exhaustion attacks
    const MAX_CHALLENGE_LENGTH = 512;      // Base64 challenge should be ~64-128 chars
    const MAX_CREDENTIALS_COUNT = 100;     // Reasonable max allowed credentials

    // Validate challenge field
    if (typeof pk.challenge !== 'string' || pk.challenge.length === 0) {
      return false;
    }
    if (pk.challenge.length > MAX_CHALLENGE_LENGTH) {
      return false;
    }

    // Validate allowCredentials if present (optional for discoverable credentials)
    if (pk.allowCredentials !== undefined && pk.allowCredentials !== null) {
      if (!Array.isArray(pk.allowCredentials)) {
        return false;
      }
      if (pk.allowCredentials.length > MAX_CREDENTIALS_COUNT) {
        return false;
      }

      // Each allowed credential must have an id with reasonable length
      for (const cred of pk.allowCredentials) {
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
