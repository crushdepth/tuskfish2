/**
 * Initialise form fields specific for data entry (new content).
 *
 * @copyright	Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license		https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since		2.0.7
 * @package		UI
 */

$(document).ready(function() {

// Initialise the datepicker date field.
$("#date").datepicker({
    "format": "yyyy-mm-dd",
    "todayHighlight": true,
    "todayBtn": 'linked',
    "startView": 'years'
});
$("#date").datepicker("setDate", new Date());
$("#date").datepicker("update");

// Initialise the datepicker expiresOn field.
$("#expiresOn").datepicker({
    "format": "yyyy-mm-dd",
    "todayHighlight": true,
    "todayBtn": "linked",
    "startView": "years"
});
$("#expiresOn").datepicker("setDate", "");
$("#expiresOn").datepicker("update");

// Initialise the fileinput widget for image field.
$("#image").fileinput({
    "showUpload": false,
    "showRemove": true,
    "allowedFileExtensions": ["gif", "jpg", "png"],
    "previewFileType": ["image"],
    "fileActionSettings": {
    "showDrag": false},
    "theme": "fa"});

// Initialise the fileinput widget for media field.
$("#media").fileinput({
    "showUpload": false,
    "showRemove": true,
    "allowedPreviewTypes": ["image", "video", "audio", "object"],
    "allowedFileExtensions": ["doc","docx","gif","gz","jpg","kml", "kmz", "mp3","mp4","odt",
        "ods", "odp", "oga","ogg","ogv","pdf","png","ppt", "pptx", "tar","wav","webm","xls",
        "xlsx", "zip"],
    "fileActionSettings": {
        "showDrag": false},
    "theme": "fa"});
});