console.log('slider-jquery.js loaded');
var clickEventType=((document.ontouchstart!==null)?'click':'touchstart');// checks if is supported click or touchstart
var isotype_cover_sliders = new Array();
var isotype_page_sliders = new Array();
( function( $ ) {
	function shuffle_children(parent){
		var children = parent.children();
		for (var i = children.length; i >= 0; i--) {
	    	parent.append(children[Math.random() * i | 0]);
		}
	}

	class isotypeSlider {
		constructor(slider_element, slides_parent_selector, index, custom_options) {
			this.slider = slider_element;
			this.slides_parent_selector = slides_parent_selector;
			this.index = index;
			this.nid = index + 1;
			this.custom_options = custom_options;
			this.options = {
				namespace: 'isotype_slider',
				slider_id: '{{namespace}}{{slider_nid}}',
				slides_tagname: 'figure',
				slide_class: '{{namespace}}_slide',
				slide_id: '{{slider_id}}_slide{{slide_nid}}',
				active_slide_class: 'active',
				before_slides: '<div class="{{namespace}}_before_slides"></div>',// false | Html that will be inserted before slides parent element
				after_slides: '<div class="{{namespace}}_after_slides"></div>',// false | Html that will be inserted before slides parent element
				controls_class: '{{namespace}}_controls',
				pager_class: '{{namespace}}_pager',
				random: false,          // Boolean: Randomize the order of the slides, true or false
				autoplay: true,			// Boolean: Animate automatically, true or false
				onclick_image: 'next',	// String: 'next' | 'random'
				speed: 500,				// Integer: Speed of the transition, in milliseconds
				timeout: 4000,			// Integer: Time between slide transitions, in milliseconds
				nav: [
					{
						html: '<nav id="{{slider_id}}_nav" class="{{namespace}}_nav">'+
							'<div class="{{namespace}}_controls">'+
								'<button class="{{namespace}}_prev" onclick="{{namespace}}s[{{index}}].slide_to(\'prev\');">Slide to prev</button>'+
								'<button class="{{namespace}}_pause" onclick="{{namespace}}s[{{index}}].pause(); $(this).css(\'display\',\'none\'); $(this).next().css(\'display\',\'initial\');">Pause</button>'+
								'<button class="{{namespace}}_play" style="display: none;" onclick="{{namespace}}s[{{index}}].play(); $(this).css(\'display\',\'none\'); $(this).prev().css(\'display\',\'initial\');">Play</button>'+
								'<button class="{{namespace}}_next" onclick="{{namespace}}s[{{index}}].slide_to(\'next\');">Slide to next</button>'+
								'<button class="{{namespace}}_shuffle" onclick="{{namespace}}s[{{index}}].shuffle();">Shuffle</button>'+
								'<button class="{{namespace}}_slide_to" onclick="{{namespace}}s[{{index}}].slide_to(2);">Slide to 2</button>'+
							'</div>'+
							'<div class="{{namespace}}_pager"></div>'+
						'</nav>',
						append_to:"this.slider.find('.{{namespace}}_before_slides')",       // Selector: Where navigation arrows should be appended to
					},
					{
						html: 'pager',
						append_to:"this.slider.find('.{{namespace}}_pager')",       // Selector: Where navigation arrows should be appended to
					}
				],
				pager: {
					ul_class: false,
					button_tagname: 'button',
					button_class: false,
				},
				pause_on_hover: false,           // Boolean: Pause on hover, true or false
				pause_on_nav_hover: false,    // Boolean: Pause when hovering controls, true or false
				maxwidth: "",           // Integer: Max-width of the slideshow, in pixels
				before_callback: false,         // Function: Before callback
				after_callback: false           // Function: After callback
			}
			//Merge custom options and default_options (this.options is updated)
			Object.assign(this.options, this.custom_options);
			this.init();
		}
		init() {
//			console.log(this.slider);
			var namespace = this.options.namespace;
			var slider_nid = this.nid;
			if(!this.slider[0].id){
				var slider_id = this.options.slider_id.replace(/\{\{namespace}\}/g, namespace).replace(/\{\{slider_nid}\}/g, slider_nid);
				this.slider[0].id = slider_id;
			}
			// Slides
			var slider = $('#'+this.slider[0].id);
//			console.log(slider);
			var $this = this;
			var slides_parent = slider.find(this.slides_parent_selector);
			var slides = slides_parent.children();
			this.slides_parent = slides_parent;
			this.slides = slides;
			var dom_parser = new DOMParser();
			if(this.options.before_slides){
				this.options.before_slides = this.options.before_slides.replace(/\{\{namespace}\}/g, namespace);
				var before_slides = $(this.options.before_slides);
				before_slides.each(function(){
					$(this).insertBefore(slides_parent);
				});
			}
			if(this.options.after_slides){
				this.options.after_slides = this.options.after_slides.replace(/\{\{namespace}\}/g, namespace);
				var after_slides = $(this.options.after_slides);
				after_slides.each(function(){
					$(this).insertBefore(slides_parent.next());
				});
			}
			if (slides.length > 1) {
				if (this.options.random) {
					$this.shuffle();
				}
				var pager = $(document.createElement('ul'));
				if(this.options.pager.ul_class){
					pager.addClass(this.options.pager.ul_class);
				}
				this.pager = pager;
//				console.log(slides);
				var $this = this;
				slides.each(function(index){
					var slide = $(this);
//					console.log(slide);
					var slide_class = $this.options.slide_class.replace(/\{\{namespace}\}/g, namespace);
					slide.addClass(slide_class);
					var slide_nid = index + 1;
					var slide_id = $this.options.slide_id.replace(/\{\{slider_id}\}/g, slider_id).replace(/\{\{slide_nid}\}/g, slide_nid);
					slide.id = slide_id;
					if($this.options.onclick_image == 'next'){
						slide.click(function(){
							$this.slide_to("next");
						});
					}
					var tab = $(document.createElement('li'));
					var tab_button = $(document.createElement($this.options.pager.button_tagname));
					var tabText = document.createTextNode(slide_nid);
					if($this.options.pager.button_class){
						tab_button.addClass($this.options.pager.button_class);
					}
					tab_button.attr('onclick', namespace+'s['+ this.index +'].slide_to('+ slide_nid +');');
					if(index == 0){
						slide.addClass($this.options.active_slide_class);
						tab.addClass($this.options.active_slide_class);
					}
					tab_button.append(tabText);
					tab.append(tab_button);
					pager.append(tab);
				});
				if(this.options.nav){
					for (var navs_index = 0; navs_index < this.options.nav.length; navs_index++) {
						var nav = this.options.nav[navs_index];
						nav.append_to = nav.append_to.replace(/\{\{slider_id}\}/g, slider_id).replace(/\{\{namespace}\}/g, namespace).replace(/\{\{index}\}/g, this.index);
						if(nav.html == 'pager'){
							var navNodes = pager;
							eval(nav.append_to).append(navNodes);
						} else {
							nav.html = nav.html.replace(/\{\{slider_id}\}/g, slider_id).replace(/\{\{namespace}\}/g, namespace).replace(/\{\{index}\}/g, this.index);
							var navNodes = $(nav.html);
							for (var navNodes_index = 0; navNodes_index < navNodes.length; navNodes_index++) {
								var navNode = navNodes[navNodes_index];
								eval(nav.append_to).append(navNode);
							}
						}
					}
				}
				if(this.options.autoplay == true){
					this.play();
				}
			}
		}
		play(){
			var $this = this;
			this.slider.addClass('playing');
			this.slider.data('playing', true);
			this.timer = setInterval(function () {
				$this.slide_to('next');
			}, this.options.timeout);
		}
		pause(){
			clearInterval(this.timer);
			console.log(this);
			this.slider.removeClass('playing');
			this.slider.data('playing', false);
		}
		shuffle(){
			shuffle_children(this.slides_parent);
		}
		tab_to(selector){
			var $this = this;
			clearInterval(this.timer);
			var pager = $(this.pager);
			var tabs = pager.find('li');
			var active_tab = pager.find('.' + this.options.active_slide_class);
			tabs.each(function(index){
				$(this).removeClass($this.options.active_slide_class);
			});
			if(selector == 'next'){
				if(active_tab.next()){
					active_tab = active_tab.next();
				} else {
					active_tab = tabs[0];
				}
			} else if(selector == 'prev'){
				if(active_tab.prev()){
					active_tab = active_tab.prev();
				} else {
					var last_tab_index = tabs.length - 1;
					active_tab = tabs[last_tab_index];
				}
			} else if(selector === parseInt(selector, 10)){
				active_tab = tabs[selector - 1];
			} else {
				alert("ERROR in tab_to(selector): selector must br 'prev', 'next' or integer.");
			}
			active_tab.addClass(this.options.active_slide_class);
			if(this.slider.data('playing') == true){
				this.play();
			}
		}
		slide_to(selector){
			var $this = this;
			var slider = this.slider;
			var slides = this.slides;
			var active_slide = this.slides_parent.find('.' + this.options.active_slide_class);
			slides.each(function(index){
				$(this).removeClass($this.options.active_slide_class);
			});
			if(selector == 'next'){
				if(active_slide.next()[0]){
					active_slide = active_slide.next();
				} else {
					active_slide = slides.first();
				}
				$this.tab_to('next');
			} else if(selector == 'prev'){
				if(active_slide.prev()[0]){
					active_slide = active_slide.prev();
				} else {
					active_slide = slides.last();
				}
				$this.tab_to('prev');
			} else if(selector === parseInt(selector, 10)){
				active_slide = slides[selector - 1];
				$this.tab_to(selector);
			} else if(selector == 'random'){

			} else {
				alert("ERROR in slide_to(selector): selector must br 'prev', 'next' or integer.");
			}
			active_slide.addClass(this.options.active_slide_class);
		}
	}
	var coverSliders = $('#cover');
	var pageSliders = $('#pages');
	coverSliders.each(function(index) {
		isotype_cover_sliders[index] = new isotypeSlider($(this), '.gallery', index, {
			namespace: 'isotype_cover_slider',
			random: false,
			nav: false
		});
	});
	pageSliders.each(function(index) {
		isotype_page_sliders[index] = new isotypeSlider($(this), '.gallery', index, {
			namespace: 'isotype_page_slider',
			random: false,
			nav: [
				{
					html: '<nav id="{{slider_id}}_nav" class="flex {{namespace}}_nav" style="display: inline-block;">'+
						'<div class="{{namespace}}_controls">'+
							'<button class="{{namespace}}_prev link" onclick="{{namespace}}s[{{index}}].slide_to(\'prev\');" title="Precedente"><i class="material-icons">chevron_left</i></button>'+
							'<button class="{{namespace}}_pause link" onclick="{{namespace}}s[{{index}}].pause(); $(this).css(\'display\', \'none\'); $(this).next().css(\'display\',\'initial\');" title="Pausa"><i class="material-icons">pause</i></button>'+
							'<button class="{{namespace}}_play link" style="display: none;" onclick="{{namespace}}s[{{index}}].play(); $(this).css(\'display\',\'none\'); $(this).prev().css(\'display\',\'initial\');" title="Avvia"><i class="material-icons">play_arrow</i></button>'+
							'<button class="{{namespace}}_next link" onclick="{{namespace}}s[{{index}}].slide_to(\'next\');" title="Successiva"><i class="material-icons">chevron_right</i></button>'+
						'</div>'+
					'</nav>',
					append_to:"$('#slider_controls')",       // Selector: Where navigation arrows should be appended to
				},
			],
		});
	});
} )( jQuery );
