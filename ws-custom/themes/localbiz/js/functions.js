console.log('Localbiz Theme functions loaded.');
// Squared sized elements
( function( $ ) {
  $('.square').each(function(){
    $this = $(this);
    var width = $this.width();
    $(this).css("min-height", width);
  });
} )( jQuery );
