/**
 * Initialise form fields specific for data editing (exising content).
 *
 * @copyright	Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license		https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since		2.0.7
 * @package		UI
 */

$(document).ready(function() {
    $('#date').datepicker({
        format: 'yyyy-mm-dd',
        todayHighlight: 'true',
        todayBtn: 'linked',
        startView: 'years'
      });
      $('#date').datepicker('setDate', new Date('<?php echo xss($content->date()->format($viewModel->dateFormat())); ?>'));
      $('#date').datepicker('update');

      $('#expiresOn').datepicker({
        format: 'yyyy-mm-dd',
        todayHighlight: 'true',
        todayBtn: 'linked',
        startView: 'years'
      });

    <?php if (!empty($content->expiresOn())): ?>
        $('#expiresOn').datepicker('setDate',
          new Date('<?php echo xss($content->expiresOn()->format($viewModel->dateFormat())); ?>'));
        $('#expiresOn').datepicker('update');
    <?php else: ?>
        $('#expiresOn').datepicker('setDate', '');
        $('#expiresOn').datepicker('update');
    <?php endif; ?>

    $("#image").fileinput({
        'showUpload': false,
        'allowedFileExtensions': ["gif", "jpg", "png"],
        'previewFileType': ["image"],
        'initialPreview': '<?php if ($content->image()) echo TFISH_IMAGE_URL . xss($content->image()); ?>',
        'initialPreviewAsData': true,
        'initialPreviewShowDelete': false,
        'initialPreviewDownloadUrl': '<?php if ($content->image()) echo TFISH_IMAGE_URL . xss($content->image()); ?>',
        'initialCaption': '<?php if (!empty($content->image())) echo xss($content->image()); ?>',
        'fileActionSettings': {
            'showDrag': false,
            'showRemove': false},
        'theme': "fa"});

    $('#image').on('fileclear', function(tf_deleteImage) {
        document.getElementById("deleteImage").value = "1";
    });

    var mimetype = $("#format").val();

    $("#media").fileinput({
      'showUpload': false,
      'initialPreview': '<?php if ($content->media()) echo TFISH_MEDIA_URL . xss($content->media()); ?>',
      'initialPreviewAsData': true,
      'initialPreviewConfig': [{type: setPreviewType(mimetype)}],
      'initialPreviewShowDelete': false,
      'initialPreviewDownloadUrl': '<?php if ($content->media()) echo TFISH_MEDIA_URL . xss($content->media()); ?>',
      'initialCaption': '<?php echo xss($content->media()); ?>',
      'allowedFileExtensions': ["doc","docx","gif","gz","jpg","kml", "kmz", "mp3","mp4","odt",
          "ods", "odp", "oga","ogg","ogv","pdf","png","ppt", "pptx", "tar","wav","webm","xls",
          "xlsx", "zip"],
      'fileActionSettings': {
          'showDrag': false,
          'showRemove': false},
      'theme': "fa"});

    $('#media').on('fileclear', function(tf_deleteMedia) {
        document.getElementById("deleteMedia").value = "1";
    });
});