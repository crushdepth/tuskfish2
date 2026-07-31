/**
 * Add interactivity to the Tuskfish CMS content management forms.
 *
 * @copyright	Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license		https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since		2.0
 * @package		UI
 */

// Shows/hides form fields that are relevant/irrelevant to the content type.
$(document).ready(function() {
    hideAlerts();

    // Show or hide content properties as appropriate for this content type.
    showHide();

    // Check the media when form loads. The warning displays faster if initiated from this position.
    checkMedia();

    // Populate the template options.
    loadTemplateOptions();

    $("#type").change(function () {
        showHide();
        checkMedia();
        loadTemplateOptions();
    });

    // Clear the expiry date if an offline item is marked online.
    $('#online').change(function() {
        if (this.value == 1) {
            $('#expiresOn').datepicker('setDate', '');
            $('#expiresOn').datepicker('update');
        }
    });

    // Copies the title to metaTitle and prefills the metaSEO string.
    $('#title').change(function(event) {
        var title = $("#title").val();
        $("#metaTitle").val(title);
        title = title.replace(/\s+/g, '-').toLowerCase();
        $("#metaSeo").val(title);
    });

    // Display a live character counter in the metaDescription field.
    $('#metaDescription').on('input', function () {
        var len = $(this).val().length;
        if (len > 160) {
            $('#metaCounter').removeClass('text-success');
            $('#metaCounter').addClass('text-danger');
        } else {
            $('#metaCounter').removeClass('text-danger');
            $('#metaCounter').addClass('text-success');
        }
        $('#metaCounter').text(len + ' characters');
    });

    // Activate the drag-and-drop file fields and wire up the media field's side effects.
    initDropGuard();
    initFileFields();
    initMediaFormatTracking();
});

// Validate the media file if content object type or selected file changes.
// Shows / hides a warning if the media file is inappropriate for the content
// type.
function checkMedia() {
    hideAlerts();

    var mimeTypes = {};
    var mediaMimeType = $('#format').val() ? $('#format').val() : '';

    // If there is no media attachment then no need to show file type warnings.
    if (mediaMimeType === '') {
        return;
    }

    // Get a list of mime types appropriate for this content type.
    switch($("#type").val()) {
        case 'TfAudio':
            mimeTypes = getAudioMimeType();
            break;

        case 'TfImage':
            mimeTypes = getImageMimeType();
            break;

        case 'TfTrack':
            mimeTypes = getTrackMimeType();
            break;

        case 'TfVideo':
            mimeTypes = getVideoMimeType();
            break;

        default:
            mimeTypes = getAllMimeType();
            break;
    }

    // You'd think Javascript would have a standard way to find values in objects, but you'd be wrong.
    var typeList = $.map(mimeTypes, function(value, index) {
        return [value];
    });

    // Show or hide the mimetype warning.
    if ($.inArray(mediaMimeType, typeList) === -1) {
        showAlerts(); // Mimetype is bad.
    }
}

// Populates the template options in the content entry / edit forms.
// NB: The options in var templateList must be kept synchronised with those in
// Content/Traits/ContentTypes.php => listTemplates(), which is the template whitelist.
function loadTemplateOptions() {
    var dropdown = $("#template");

    dropdown.empty();

    var templateList = {
        "TfArticle":
            {"article": "Center image", "article-left": "Left image", "article-right": "Right image"},
        "TfAudio":
            {"audio": "Audio"},
        "TfBlock":
            {"block": "Block"},
        "TfCollection":
            {"collection": "Detailed view", "collection-compact": "Compact view", "collection-gallery": "Gallery", "collection-gallery-full": "Gallery (full-width image)"},
        "TfDownload":
            {"download": "Download"},
        "TfImage":
            {"image": "Image"},
        "TfTag":
            {"tag": "Tag"},
        "TfTrack":
            {"track": "Track"},
        "TfVideo":
            {"video16x9": "Video 16x9", "video4x3": "Video 4x3", "video21x9": "Video 21x9", "video1x1": "Video 1x1"},
    };

    $.each(templateList[$("#type").val()], function(index, value) {
        var st = $("#selectedTemplate");
        if (st.length && st.val() == index) {
            dropdown.append($("<option></option>").attr({'value': index, 'selected': 'true'}).text(value));
        } else {
        dropdown.append($("<option></option>").attr('value', index).text(value));
        }
    });
}

// File upload fields.
//
// These are progressive enhancement over a plain <input type="file">: with scripting disabled the
// form still selects and uploads files, it just loses the drop zone, preview and inline warnings.
// Everything here is vanilla JS and uses no library. All configuration is read from the markup, so
// the templates remain the single source of truth for labels and whitelists.
//
// Three custom events are dispatched on the file input so that fields can attach side effects
// without this code needing to know about them:
//
//   tf:fileselect  A valid file was chosen or dropped. detail: {file}
//   tf:fileclear   The pending selection was cleared.
//   tf:filedelete  The stored file was marked (or unmarked) for deletion. detail: {deleted}

// Stops a file dropped anywhere other than a drop zone from being opened by the browser.
//
// The default action for a file dropped on a page is to navigate to it, which would discard a
// half-completed form. A drop zone is a target worth aiming at, and therefore worth missing: a drag
// that lands just outside the dashed border would otherwise cost the user everything they had typed.
//
// Drags into the TinyMCE editor are unaffected, as its editable body is in an iframe and its events
// do not reach this document.
function initDropGuard() {
    ['dragover', 'drop'].forEach(function (name) {
        document.addEventListener(name, function (event) {
            // Only files are intercepted. Dragging text within the form must keep working.
            if (!event.dataTransfer || !dragCarriesFiles(event.dataTransfer)) {
                return;
            }

            // Drops on a zone are the zone's business; it calls preventDefault itself.
            if (event.target && event.target.closest && event.target.closest('.tf-filefield-dropzone')) {
                return;
            }

            // Cancelling the default action alone would leave the browser showing a "you may drop
            // here" cursor over ground where the drop is silently discarded. Marking the target as
            // invalid gives the no-entry cursor instead, which points the user at the drop zone.
            if (name === 'dragover') {
                event.dataTransfer.dropEffect = 'none';
            }

            event.preventDefault();
        });
    });
}

// Reports whether a drag is carrying files, as opposed to text or a link. The types property is a
// DOMStringList in some browsers and an array in others, so it is not safe to assume indexOf().
function dragCarriesFiles(dataTransfer) {
    var types = dataTransfer.types;

    if (!types) {
        return false;
    }

    for (var i = 0; i < types.length; i++) {
        if (types[i] === 'Files') {
            return true;
        }
    }

    return false;
}

function initFileFields() {
    var fields = document.querySelectorAll('.tf-filefield');

    for (var i = 0; i < fields.length; i++) {
        initFileField(fields[i]);
    }
}

function initFileField(wrapper) {
    var input = wrapper.querySelector('input[type="file"]');

    if (!input) {
        return;
    }

    var dropzone = wrapper.querySelector('.tf-filefield-dropzone') || wrapper;
    var preview = wrapper.querySelector('.tf-filefield-preview');
    var error = wrapper.querySelector('.tf-filefield-error');
    var clearButton = wrapper.querySelector('.tf-filefield-clear');
    var deleteButton = wrapper.querySelector('.tf-filefield-delete');

    // The accept attribute is already the client-side whitelist, so there is no need to emit it a
    // second time as JSON. Note that accept only filters the operating system's file picker; it does
    // not filter dropped files, which is why the check below is repeated in script.
    var extensions = parseAcceptAttribute(input.accept);
    var maxBytes = parseInt(wrapper.dataset.maxBytes, 10) || 0;
    var objectUrl = '';

    // Drag and drop. A file input is a drop target in its own right, but the target is only as big
    // as the control. Handling the events on a wrapper gives something worth aiming at.
    ['dragenter', 'dragover'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragend', 'drop'].forEach(function (name) {
        dropzone.addEventListener(name, function () {
            dropzone.classList.remove('is-dragging');
        });
    });

    // dragleave bubbles from descendants, so a pointer crossing the hint text or the preview would
    // otherwise flicker the highlight off and on. Only a departure from the zone itself counts.
    dropzone.addEventListener('dragleave', function (event) {
        if (event.relatedTarget && dropzone.contains(event.relatedTarget)) {
            return;
        }

        dropzone.classList.remove('is-dragging');
    });

    dropzone.addEventListener('drop', function (event) {
        event.preventDefault();

        if (!event.dataTransfer || !event.dataTransfer.files.length) {
            return;
        }

        // Assigning to input.files is what makes a custom drop zone possible without a library: the
        // dropped file becomes the input's selection and is submitted with the form as usual.
        //
        // A drop can carry several files even though these inputs accept only one. Assigning the
        // list wholesale would submit every one of them under the same field name, leaving PHP to
        // keep the last while the preview showed the first. Copying through a DataTransfer trims
        // the list to what the input actually accepts.
        if (input.multiple) {
            input.files = event.dataTransfer.files;
        } else {
            var transfer = new DataTransfer();
            transfer.items.add(event.dataTransfer.files[0]);
            input.files = transfer.files;
        }

        input.dispatchEvent(new Event('change', {'bubbles': true}));
    });

    // Click-to-browse anywhere in the zone, not just on the input's own button. Clicks that landed
    // on the input or on a control of its own are left alone, which also stops the synthetic click
    // below from re-entering this handler.
    dropzone.addEventListener('click', function (event) {
        if (event.target === input || event.target.closest('button, a, label')) {
            return;
        }

        input.click();
    });

    input.addEventListener('change', function () {
        var file = input.files.length ? input.files[0] : null;

        hideError();

        if (!file) {
            clearSelection();
            return;
        }

        var message = validateFile(file);

        // A rejected file leaves the field empty, so it is reported as a clear: anything tracking
        // the selection (the media format, for one) must not be left describing a file that is no
        // longer there.
        if (message) {
            clearSelection();
            showError(message);
            return;
        }

        renderPreview(file);

        if (clearButton) {
            clearButton.hidden = false;
        }

        input.dispatchEvent(new CustomEvent('tf:fileselect', {'bubbles': true, 'detail': {'file': file}}));
    });

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            clearSelection();
            hideError();
        });
    }

    // Marks the file already stored on the server for deletion. This is a toggle rather than a
    // one-way action, so that a mistaken click can be undone without abandoning the form.
    //
    // The button is shipped hidden and revealed here, because it does nothing without this handler:
    // with scripting off it would be a visible control that silently ignores every click.
    if (deleteButton) {
        deleteButton.hidden = false;

        deleteButton.addEventListener('click', function () {
            var flag = document.getElementById(wrapper.dataset.deleteFlag);

            if (!flag) {
                return;
            }

            var current = wrapper.querySelector('.tf-filefield-current');
            var note = wrapper.querySelector('.tf-filefield-deletenote');
            var deleted = flag.value !== '1';

            flag.value = deleted ? '1' : '0';
            deleteButton.textContent = deleted ? deleteButton.dataset.labelUndo : deleteButton.dataset.labelRemove;

            if (current) {
                current.classList.toggle('is-marked-for-deletion', deleted);
            }

            if (note) {
                note.hidden = !deleted;
            }

            input.dispatchEvent(new CustomEvent('tf:filedelete', {'bubbles': true, 'detail': {'deleted': deleted}}));
        });
    }

    // Checks a file against the whitelist and size ceiling. Both are conveniences that save a failed
    // round trip; the server performs its own checks and is the actual gate.
    function validateFile(file) {
        if (extensions.length) {
            if (extensions.indexOf(getFileExtension(file.name)) === -1) {
                return (wrapper.dataset.msgType || '') + ' ' + extensions.join(', ') + '.';
            }
        }

        if (maxBytes > 0 && file.size > maxBytes) {
            return (wrapper.dataset.msgSize || '') + ' ' + formatBytes(maxBytes) + '.';
        }

        return '';
    }

    function renderPreview(file) {
        if (!preview) {
            return;
        }

        clearPreview();

        objectUrl = URL.createObjectURL(file);

        var node = previewNodeFor(file, objectUrl);

        if (node) {
            preview.appendChild(node);
        }

        var caption = document.createElement('p');
        caption.className = 'tf-filefield-caption';
        caption.textContent = file.name + ' (' + formatBytes(file.size) + ')';
        preview.appendChild(caption);

        preview.hidden = false;
    }

    function previewNodeFor(file, url) {
        var node;

        if (file.type.indexOf('image/') === 0) {
            node = new Image();
            node.src = url;
            node.alt = '';

            return node;
        }

        if (file.type.indexOf('audio/') === 0 || file.type.indexOf('video/') === 0) {
            node = document.createElement(file.type.indexOf('audio/') === 0 ? 'audio' : 'video');
            node.src = url;
            node.controls = true;

            return node;
        }

        if (file.type === 'application/pdf') {
            node = document.createElement('embed');
            node.src = url;
            node.type = 'application/pdf';

            return node;
        }

        // Anything else (documents, archives, GPS tracks) has no useful inline representation, so
        // the caption alone reports what was selected.
        return null;
    }

    function clearPreview() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = '';
        }

        if (preview) {
            preview.replaceChildren();
            preview.hidden = true;
        }
    }

    // Empties the field and announces it. Every route back to "no file chosen" goes through here so
    // that the input, the preview and any listener stay in agreement.
    function clearSelection() {
        input.value = '';
        clearPreview();

        if (clearButton) {
            clearButton.hidden = true;
        }

        input.dispatchEvent(new CustomEvent('tf:fileclear', {'bubbles': true}));
    }

    function showError(message) {
        if (error) {
            // Revealed before the text is set: a live region that is still hidden when its content
            // changes is not reliably announced by screen readers.
            error.hidden = false;
            error.textContent = message;
        }
    }

    function hideError() {
        if (error) {
            error.textContent = '';
            error.hidden = true;
        }
    }
}

// Keeps the format (mimetype) property and the content-type compatibility warning in step with the
// media field. The format is what the front end uses to choose an inline player, so it has to follow
// the file rather than be entered by hand.
function initMediaFormatTracking() {
    var media = document.getElementById('media');
    var format = document.getElementById('format');

    if (!media || !format) {
        return;
    }

    // The format of the file already stored on the server, restored if a pending change is undone.
    var savedFormat = format.value;

    // The format of a file chosen but not yet uploaded, if any. A pending file outranks the stored
    // one, because it is what the server will end up saving.
    var pendingFormat = null;

    var setFormat = function (value) {
        format.value = value;
        checkMedia();
    };

    var storedFormat = function () {
        var flag = document.getElementById('deleteMedia');

        return (flag && flag.value === '1') ? '' : savedFormat;
    };

    media.addEventListener('tf:fileselect', function (event) {
        var mimeTypes = getAllMimeType();
        var extension = getFileExtension(event.detail.file.name);

        pendingFormat = mimeTypes[extension] ? mimeTypes[extension] : '';
        setFormat(pendingFormat);
    });

    // Clearing a pending selection falls back to the stored file, unless that is marked for deletion.
    media.addEventListener('tf:fileclear', function () {
        pendingFormat = null;
        setFormat(storedFormat());
    });

    // Marking the stored file for deletion says nothing about a file waiting to replace it, so the
    // pending format survives the toggle.
    media.addEventListener('tf:filedelete', function () {
        setFormat(pendingFormat !== null ? pendingFormat : storedFormat());
    });
}

// Reads a file input's accept attribute into a list of lowercase extensions. Entries that are
// mimetypes or wildcards rather than extensions are ignored, as they cannot be matched by name.
function parseAcceptAttribute(accept) {
    var extensions = [];

    if (!accept) {
        return extensions;
    }

    accept.split(',').forEach(function (entry) {
        entry = entry.trim().toLowerCase();

        if (entry.charAt(0) === '.' && entry.length > 1) {
            extensions.push(entry.slice(1));
        }
    });

    return extensions;
}

// Formats a byte count for display, eg. 8388608 => "8 MB".
function formatBytes(bytes) {
    var units = ['bytes', 'KB', 'MB', 'GB'];
    var unit = 0;

    while (bytes >= 1024 && unit < units.length - 1) {
        bytes = bytes / 1024;
        unit++;
    }

    return (unit === 0 ? bytes : Math.round(bytes * 10) / 10) + ' ' + units[unit];
}

// Show warning if media file type is inappropriate for this content type.
function showAlerts() {
    $('.alert').removeClass('d-none');
    $('.alert').removeClass('hide');
    $('.alert').addClass('d-block');
    $('.alert').addClass('show');
    $('.alert2').removeClass('d-none');
    $('.alert2').removeClass('hide');
    $('.alert2').addClass('d-inline');
    $('.alert2').addClass('show');
}

// Hide warning if media file type is inappropriate for this content type.
function hideAlerts() {
    $('.alert').removeClass('d-block');
    $('.alert').removeClass('show');
    $('.alert').addClass('d-none');
    $('.alert').addClass('hide');
    $('.alert2').removeClass('d-inline');
    $('.alert2').removeClass('show');
    $('.alert2').addClass('d-none');
    $('.alert2').addClass('hide');
}

// Gets the file extension of the media file (used to set mimetype). Lowercased, as the mimetype
// lists are keyed on lowercase extensions and cameras commonly produce names like "PHOTO.JPG".
function getFileExtension(filename) {
    return filename.slice((filename.lastIndexOf(".") - 1 >>> 0) + 2).toLowerCase();
}

// NB: The mimetype lists below must be kept synchronised with those in
// Traits/Mimetypes.php, which is the server-side upload whitelist.

// Get an audio mimetype.
function getAudioMimeType() {
    var audioMimeType = {};

    audioMimeType.mp3 = "audio/mpeg";
    audioMimeType.oga = "audio/ogg";
    audioMimeType.ogg = "audio/ogg";
    audioMimeType.wav = "audio/x-wav";

    return audioMimeType;
}

// Get an image mimetype.
function getImageMimeType() {
    var imageMimeType = {};

    imageMimeType.gif = "image/gif";
    imageMimeType.jpg = "image/jpeg";
    imageMimeType.jpeg = "image/jpeg";
    imageMimeType.png = "image/png";

    return imageMimeType;
}

// Get a track mimetype.
function getTrackMimeType() {
    var trackMimeType = {};

    trackMimeType.kml = "application/vnd.google-earth.kml+xml";
    trackMimeType.kmz = "application/vnd.google-earth.kmz";

    return trackMimeType;
}

// Get a video mimetype.
function getVideoMimeType() {
    var videoMimeType = {};

    videoMimeType.mp4 = "video/mp4";
    videoMimeType.ogv = "video/ogg";
    videoMimeType.webm = "video/webm";

    return videoMimeType;
}

// Get the appropriate mimetype, given a file extension.
function getAllMimeType() {
    var audioMimeTypes = getAudioMimeType();
    var imageMimeTypes = getImageMimeType();
    var trackMimeTypes = getTrackMimeType();
    var videoMimeTypes = getVideoMimeType();

    var allMimeTypes = Object.assign({}, audioMimeTypes, imageMimeTypes, trackMimeTypes, videoMimeTypes);

    // Add documents.
    allMimeTypes.doc = "application/msword";
    allMimeTypes.docx = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    allMimeTypes.pdf = "application/pdf";
    allMimeTypes.ppt = "application/vnd.ms-powerpoint";
    allMimeTypes.pptx = "application/vnd.openxmlformats-officedocument.presentationml.presentation";
    allMimeTypes.odt = "application/vnd.oasis.opendocument.text";
    allMimeTypes.ods = "application/vnd.oasis.opendocument.spreadsheet";
    allMimeTypes.odp = "application/vnd.oasis.opendocument.presentation";
    allMimeTypes.xls = "application/vnd.ms-excel";
    allMimeTypes.xlsx = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    // Add archives.
    allMimeTypes.zip = "application/zip";
    allMimeTypes.gz = "application/x-gzip";
    allMimeTypes.tar = "application/x-tar";

    // Add KML tracks for GPS.
    allMimeTypes.kml = "application/vnd.google-earth.kml+xml";
    allMimeTypes.kmz = "application/vnd.google-earth.kmz";

    return allMimeTypes;
}

// Shows or hides form fields as appropriate for this content type.
function showHide() {
    var allowedProperties = ['teaserContainer', 'descriptionContainer',
        'captionContainer','creatorContainer', 'dateContainer', 'expiresOnContainer',
        'imageContainer', 'languageContainer','mediaContainer',
        'parentContainer', 'publisherContainer', 'templateContainer',
        'rightsContainer', 'tagsContainer', 'metaHeader', 'metaTitleContainer',
        'seoContainer', 'metaDescriptionContainer'];

    $.each(allowedProperties, function (i, value) {
        $('#' + value).show();
    });
    if ($("#type").val() === 'TfTag') {
        var disabledProperties = [
            'creatorContainer', 'expiresOnContainer', 'languageContainer', 'rightsContainer',
            'publisherContainer', 'tagsContainer'];
        $.each(disabledProperties, function (i, value) {
            $('#' + value).hide();
        });
    }
    if ($("#type").val() === 'TfVideo') {
        $("#externalMediaContainer").show();
    } else {
        $("#externalMediaContainer").hide();
    }
    if ($("#type").val() === 'TfBlock') {
        var disabledProperties = [
            'teaserContainer', 'creatorContainer', 'parentContainer',
            'rightsContainer', 'publisherContainer', 'metaHeader', 'metaTitleContainer',
            'seoContainer', 'metaDescriptionContainer'];
        $.each(disabledProperties, function (i, value) {
            $('#' + value).hide();
        });
    }
}
