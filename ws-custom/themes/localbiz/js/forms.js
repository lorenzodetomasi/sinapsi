console.log('forms.js loaded')
function if_checked(action, target, args){
	//if a checkbox is checked, show or hide another element by id
	//action: slideUp | slideDown;
	//target: [jQuery selector]
	//args:
	$this = jQuery(this);
//	console.log($this);
	var fastInput = form.find('input[name="fast"]');
	var dateField = fastInput.parent().next();
	dateField.addClass('display-none');
	fastInput.change(function(){
		dateField.toggleClass('display-none');
	});
}
( function( $ ) {
//	$( function() {
		$.fn.exists = function () {
			return this.length !== 0;
		}
		function isotype_toggle_submit(is_validated, submit){
			if(is_validated == true){
				submit.removeAttr('disabled');
			} else {
				submit.attr('disabled','disabled');
				console.log(is_validated);
			}
		}
		function isotype_form_is_validated(required_fields){
			var is_validated = true;
			required_fields.each(function(){
				$this = $(this);
				$this.removeClass('required-field');
				if($this.is('input:text') || $this.is('input[type="email"]') || $this.is('textarea') || $this.is('input[type="date"]') || $this.is('input[type="time"]')){
					if(!$this.val()){
						is_validated = false;
						$this.addClass('required-field');
					}
				} else {
					if(!$this.is(':checked')){
						is_validated = false;
						$this.addClass('required-field');
					}
				}
			});
			return is_validated;
		}
		var isotype_form_init = function(form){
			var name = form.attr('name');
			var alertboxID = 'form_inside_alertbox-'+name;
			var submit = form.find('[type="submit"]');
			var required_fields = form.find('[required]');
			//http://tympanus.net/codrops/2014/07/10/inspiration-for-custom-select-elements/
			//https://harvesthq.github.io/chosen/
			//https://select2.github.io/
			//http://www.lessanvaezi.com/filter-select-list-options/
			if(required_fields && submit){
				isotype_toggle_submit(isotype_form_is_validated(required_fields), submit);
				required_fields.change(function(){
					isotype_toggle_submit(isotype_form_is_validated(required_fields), submit);
				});
			}
			form.append('<div id="'+alertboxID+'"></div>');
			var alertbox = $("#"+alertboxID);
			form.on( "submit", function( event ) {
				isotype_urls();
				var emailInput = form.find('input[name="email"]');
				var ajax_return = form.find('input[name="_ajax"]').val();
				if(emailInput && ajax_return){
					event.preventDefault();
					alertbox.html($('<p>'+'Invio in corso…'+'</p>'));
					var submitted_values = form.serialize();
					var data = new FormData(form[0]);
					$.ajax({
						type: "POST",
						url: form.attr('action'),
						data: data,
						contentType: false,
						processData: false,
						//Error report
						success: function(msg){
							var msgPart = $(msg).find(ajax_return);
							var output;
							if(msgPart.length > 0){
								output = msgPart;
							} else {
								output = 'La tua richiesta è stata inviata.';
							}
							alertbox.html(output);
						},
						error: function(){
							alertbox.html("Si è verificato un errore. Riprova o contattaci via email.");
						}
					});
					var from = emailInput.val();
					var mailchimp_form_action = $('[name="_mailchimp_form_action"]').val();
					var mailchimp_subscription = $('[name="mailchimp_subscription"]').val();
					if( mailchimp_subscription == 'on' && mailchimp_form_action && from){
						mailchimp_form_action = mailchimp_form_action.replace('/post?', '/post-json?').concat('&c=?');
						$.ajax({
							type: "GET",
							url: mailchimp_form_action,
							data: {
								EMAIL: from
							},
							dataType: 'jsonp',
							success: function (data) {
//								console.log(data['msg']);
							}
						});
					}
				}
			});
		}
		function isotype_next_li(list){
			selected = list.find('.selected');
			if(selected.length == 0){
				list.find('> ul > li:first-child').addClass('selected');
			} else if(selected.length > 0){
				selected.removeClass('selected');
				selectedChildren = selected.find('> ul > li:first-child');
				if(selectedChildren.length > 0){
					selectedChildren.first().addClass('selected');
				} else {
					if(selected.is(':last-child')){
						selected.parent().parent().next().addClass('selected');
					} else {
						selected.next().addClass('selected');
					}
				}
			}
		}
		function isotype_prev_li(list){
			selected = list.find('.selected');
			if(selected.length == 0){
				list.find('li').last().addClass('selected');
			} else if(selected.length > 0){
				selected.removeClass('selected');
				selectedChildren = selected.find('> ul > li:last-child');
				if(selectedChildren.length > 0){
					selectedChildren.last().addClass('selected');
				} else {
					if(selected.is(':first-child')){
						selected.parent().parent().addClass('selected');
					} else {
						selected.prev().addClass('selected');
					}
				}
			}
		}
		function isotype_select_li(selectedLi, datalistField){
			var selected = selectedLi.children('a');
			var selectedValue = selected.attr('data-value');
			var selectedText = selected.find('.text').text();
			datalistField.find('input[type="hidden"]').val(selectedValue);
			datalistField.find('input[data-list]').val(selectedText);
		}
		function isotype_datalists(){
			var fieldsDatalist = $('.field.data-list');
			fieldsDatalist.each(function(){
				var field = $(this);
				var inputWithDatalist = field.find('input[data-list]');
				inputWithDatalist.attr('autocomplete', 'off');
				var listID = inputWithDatalist.attr('data-list');
				var inputName = inputWithDatalist.attr('name');
				var hiddenValueInputElement = inputWithDatalist.after('<input type="hidden" name="'+inputName+'-id" />');
				var list = $('#'+listID);
				list.css({
					'top': field.offset().top+field.height(),
					'left': field.offset().left
				});
				list.hide();
				inputWithDatalist.focusin(function(){
					list.find('.selected').removeClass('selected');
					list.show();
				});
				inputWithDatalist.focusout(function(){
					setTimeout(function(){
						list.hide();
					}, 100);
				});
				var allOptions = list.find('li');
				var parentOptions = list.find('li:not([data-childof])');
				parentOptions.append($('<ul></ul>'));
				var childOptions = list.find('li[data-childof]');
				childOptions.each(function(){
					var child = $(this);
					var parentNames = child.attr('data-childof').split(',');
					$.each(parentNames, function(index, value){
						var parent = $('[data-value="'+value+'"]');
						if(parentNames.length == 1){
							child.appendTo(parent.find('ul'));
						}
					});
				});
				var synonyms = list.find('[data-synonymof]').addClass="display-none";
				inputWithDatalist.keydown(function(e) {
					if (e.keyCode == 13) {
						//Enter pressed
						isotype_select_li(list.find('.selected'), field);
					}
					if (e.keyCode == 40){
						//Arrow down pressed
						isotype_next_li(list);
					} else if (e.keyCode == 38){
						//Arrow up pressed
						isotype_prev_li(list);
					}
				});
				inputWithDatalist.keyup(function(){
					var inputValue = $(this).val().toLowerCase();
					if(inputValue == ""){
						allOptions.show();
					} else {
						allOptions.each(function(){
							var text = $(this).text().toLowerCase();
							(text.indexOf(inputValue) >= 0) ? $(this).show() : $(this).hide();
						});
					}
				});
				list.find('a').click(function(event){
					event.preventDefault();
					isotype_select_li($(this).closest('li'), field);
				});
			});
		}
		function ws_repeats_init(){
			ws_repeats_initButtons();
			var repeats = $('.repeat');
			var template = repeats.find('.repeat-item').first();
			repeats.each(function(){
				var repeat = $(this);
				ws_repeats_updateDeleteButtons(repeat);
				var templateID = template.attr('id');
				var templateClone = template.clone();
				templateClone.removeClass('repeat-item');
				templateClone.addClass('display-none');
				var templateCloneID = templateClone.attr('id');
				var fieldID = templateCloneID.substr(0, templateCloneID.indexOf('__'));
				var fieldRegExp = new RegExp("(" + fieldID + ')\\[.*?\\]', "");
				$.each(templateClone.find('[name]'), function(index, value){
					field = $(this);
					var nameValue = field.attr('name');
					var newNameValue = nameValue.replace(fieldRegExp, '$1[X]');
					field.attr('data-name', newNameValue);
					field.removeAttr('name');
				});
				templateCloneID = templateCloneID.substring(0, templateCloneID.lastIndexOf('__') + 2) + 'X';
				templateClone.attr('id', templateCloneID);
				template.removeClass('repeat-template');
				repeat.before(templateClone);
			});
		}
		function ws_repeats_initButtons(){
			var insertButtons = $('.repeat-insert');
			insertButtons.on('click', function(){
				ws_repeats_insertItem($(this));
			});
			var deleteButtons = $('.repeat-delete');
			deleteButtons.on('click', function(){
				ws_repeats_deleteItem($(this));
			});
		}
		// If .repeat DOM changes
		$('.repeat').bind('DOMSubtreeModified', function(){
			var repeat = $(this);
//			console.log('Modified element: ');
//			console.log(repeat);
			ws_repeats_updateDeleteButtons(repeat);
			ws_repeats_updateIndexes(repeat);
		});
		function ws_repeats_updateDeleteButtons(repeat){
			deleteButtons = repeat.find('.repeat-item .repeat-delete');
			if(deleteButtons.length == 1){
				deleteButtons.attr('disabled', 'disabled');
			} else if(deleteButtons.length > 1){
				deleteButtons.removeAttr('disabled');
			}
		}
		function ws_repeats_updateIndexes(repeat){
			var repeatItems = repeat.children('.repeat-item');
			$.each(repeatItems, function(index, value){
				var repeatItem = $(repeatItems[index]);
				var repeatItemID = repeatItem.attr('id');
				repeatItem.attr('id', repeatItemID.substring(0, repeatItemID.lastIndexOf('__') + 2) + index);
				var deleteButton = repeatItem.find('.repeat-delete');
				var deleteAt = deleteButton.data('at');
				deleteButton.attr('data-at', deleteAt.substring(0, deleteAt.lastIndexOf('__') + 2) + index);
				var fieldID = repeatItemID.substr(0, repeatItemID.indexOf('__'));
				var fieldRegExp = new RegExp("(" + fieldID + ')\\[.*?\\]', "");
				repeatItem.find('[name]').each(function(){
					field = $(this);
//					console.log(field);
					var nameValue = field.attr('name');
					var newNameValue = nameValue.replace(fieldRegExp, '$1[' + index + ']');
					field.attr('name', newNameValue);
				});
			});
		}
		// Drag & Drop Sortable items
		// https://codepen.io/fitri/pen/VbrZQm
		(()=> {enableDragSort('sortable')})();
		function enableDragSort(listClass) {
		  const sortableLists = document.getElementsByClassName(listClass);
		  Array.prototype.map.call(sortableLists, (list) => {enableDragList(list)});
		}
		function enableDragList(list) {
		  Array.prototype.map.call(list.children, (item) => {enableDragItem(item)});
		}
		function enableDragItem(item) {
		  item.setAttribute('draggable', true)
		  item.ondrag = handleDrag;
		  item.ondragend = handleDrop;
		}
		function handleDrag(item) {
		  const selectedItem = item.target,
		        list = selectedItem.parentNode,
		        x = event.clientX,
		        y = event.clientY;
		  selectedItem.classList.add('drag-sort-active');
		  let swapItem = document.elementFromPoint(x, y) === null ? selectedItem : document.elementFromPoint(x, y);
		  if (list === swapItem.parentNode) {
		    swapItem = swapItem !== selectedItem.nextSibling ? swapItem : swapItem.nextSibling;
		    list.insertBefore(selectedItem, swapItem);
		  }
		}
		function handleDrop(item) {
		  item.target.classList.remove('drag-sort-active');
		}

		function ws_repeats_deleteItem(deleteButton){
			var at = $(deleteButton.data('at'));
			var repeatItems = at.siblings('.repeat-item');
			if(repeatItems.length > 0){
				at.remove();
			}
		}
		function ws_repeats_insertItem(insertButton){
			var at = $(insertButton.data('at'));
			var template = at.prev();
			var templateClone = template.clone();
			var repeatItems = template
			var copyIndex = repeatItems.index(template) + 1;
			var copyIndexElement = templateClone.find('[data-copy-index]');
			copyIndexElement.val(copyIndexElement.val() + ' (copia)')
			var deleteButton = templateClone.find('.repeat-delete');
			templateClone.removeClass('repeat-template display-none');
			var fields = templateClone.find('[data-name]');
			fields.each(function(){
				var field = $(this);
				var nameValue = field.attr('data-name');
				field.attr('name', nameValue);
				field.removeAttr('data-name');
			});
			templateClone.addClass('repeat-item');
			deleteButton.on('click', function(){
				ws_repeats_deleteItem($(this));
			});
			at.append(templateClone);
		}
		function init_googlemaps_places_autocomplete(inputs, type) {
			inputs = $(".field.postal-address input");
			type = typeof type !== 'undefined' ? type : null;
			$.each(inputs, function(index, value){
				var input = inputs[index];
				new google.maps.places.Autocomplete(
					(input),
					{
						type: type//https://developers.google.com/places/supported_types
					}
				);
			});
		}
		function isotype_urls(){
			var fieldsUrl = $('[type="url"]');
			var prefix = 'http://';
			fieldsUrl.each(function(){
				var $field = $(this);
				var url = $field.val();
				if (url.substr(0, prefix.length) !== prefix){
					url = prefix + url;
					$field.val(url);
//					console.log(url);
				}
			});
		}
		isotype_datalists();
		ws_repeats_init();
		init_googlemaps_places_autocomplete();
		var forms = $('form');
		forms.each(function(){
			form = $(this);
			isotype_form_init(form);
		});
//	});
} )( jQuery );
