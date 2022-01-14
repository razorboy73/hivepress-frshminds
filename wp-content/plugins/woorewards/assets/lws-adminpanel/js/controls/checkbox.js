(function($){$.widget("lws.lws_checkbox",{options:{iconChecked:"lws-icon-checkmark",iconUnchecked:"",iconColor:"#3fa9f5",size:false,borderColor:"#aaa",classNames:""},_create:function(){this._getDataOptions();this.disabled=this.element.attr("disabled")=="disabled"?"disabled":"";this.newCB=$("<div>").addClass("lws-checkbox").addClass(this.options.classNames).insertAfter(this.element);if(this.options.size){this.newCB.css("width",this.options.size+"px").css("height",this.options.size+"px");this.newCB.css("line-height",parseInt(this.options.size)-4+"px");this.newCB.css("font-size",parseInt(this.options.size)-4+"px")}this.element.addClass("lws-checkbox-hidden");this.newCB.css("color",this.options.iconColor);if(this.disabled=="disabled"){this.setInactive()}else{this.setActive()}this.element.on("change",this._bind(this.refresh,this));this.newCB.on("classChange",this._bind(this._classChange,this));this.inHouse=true;this.element.object=this},_bind:function(fn,me){return function(){return fn.apply(me,arguments)}},_getDataOptions:function(){if(this.element.data("size")!=undefined)this.options.size=this.element.data("size");if(this.element.data("class")!=undefined)this.options.classNames=this.element.data("class");if(this.element.data("icon-color")!=undefined)this.options.iconColor=this.element.data("icon-color");if(this.element.data("icon-unchecked")!=undefined)this.options.iconUnchecked=this.element.data("icon-unchecked");if(this.element.data("icon-checked")!=undefined)this.options.iconChecked=this.element.data("icon-checked")},_clickCheckbox:function(event){if(this.element.data("active")!="yes")return;this.element.prop("checked",!this.element.prop("checked")).trigger("change")},_classChange:function(event){if(this.newCB.hasClass("lws-checkbox-inactive")){this.setInactive()}else{this.setActive()}},refresh:function(event){if(this.element.data("active")!="yes")return;if(this.element.prop("checked")){this.newCB.removeClass(this.options.iconUnchecked);this.newCB.addClass("lws-checkbox-checked "+this.options.iconChecked)}else{this.newCB.removeClass("lws-checkbox-checked "+this.options.iconChecked);this.newCB.addClass(this.options.iconUnchecked)}},setInactive:function(){this.newCB.off("click");this.element.data("active","no");this.element.prop("checked",false);this.newCB.removeClass("lws-checkbox-checked");this.newCB.removeClass(this.options.iconChecked);this.newCB.removeClass(this.options.iconUnchecked);this.newCB.addClass("lws-checkbox-inactive")},setActive:function(){if(this.element.data("active")!="yes"){this.newCB.on("click",this._bind(this._clickCheckbox,this));this.element.data("active","yes");this.refresh();this.newCB.removeClass("lws-checkbox-inactive")}}})})(jQuery);jQuery(function($){$(".lws_checkbox").lws_checkbox()});
