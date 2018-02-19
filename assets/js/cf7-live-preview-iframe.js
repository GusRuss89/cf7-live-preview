// This closure gives access to jQuery as $
// Don't delete it
(function($) {

  // Do stuff
  $(document).ready(function() {
    
    // Tell parent frame we're ready
    window.parent.postMessage({
      cf7lp: true,
      type: 'cf7lp-ready'
    }, window.origin)

    // Continually send height to parent for resizing
    setInterval(function(){
      window.parent.postMessage({
        cf7lp: true,
        type: 'cf7lp-iframe-height',
        height: $('#form-container').outerHeight()
      }, window.origin)
    }, 500)
    
    // Receive message
    function receiveMessage(event) {
      if (!event.data.hasOwnProperty('cf7lp') || event.origin !== window.origin)
        return
      
      switch( event.data.type ) {

        // Update background colour
        case 'background':
          $('#form-container').css('background-color', event.data.value)
          break

        // Inject new html
        case 'formUpdate':
        default:
          var data = {
            action: 'cf7lp_update_preview',
            form_editor_value: event.data.formVal
          }
    
          $.post(ajax_object.ajax_url, data, function(response) {
            $('#form-container').html(response)
          })
          break
      } 

      //event.source.postMessage('test', event.origin)
    }

    window.addEventListener('message', receiveMessage, false);

  })

})(jQuery)
