(function($){$.widget("lws.lar_rewards",{_create:function(){if(!this.element.hasClass("extended")){this._createTitlesOverflow();this.element.find(".lar-accordeon-expanded-cont").hide();this.element.on("mouseenter",".lar-accordeon-not-expanded-cont",this._bind(this._enterTitle,this));this.element.on("mouseleave",".lar-accordeon-not-expanded-cont",this._bind(this._leaveTitle,this));this.element.on("click",".lar-accordeon-not-expanded-cont",this._bind(this._showContent,this));this.element.on("click",".lar-expanded-line",this._bind(this._hideContent,this))}this.element.on("click",".lar-unlockable-unlock-btn",this._bind(this._clickUnlock,this));this.element.on("click",".lar-unlockable-confirm-no",this._bind(this._clickNo,this))},_bind:function(fn,me){return function(){return fn.apply(me,arguments)}},_createTitlesOverflow:function(){$(".lar_overflow").each(function(i,obj){$(obj).animStatus="idle";var animo=$("<div>",{class:"lar_aoverflow lar-flrow lar-overcolors",css:{width:"0px",display:"none"}}).html($(obj).html()).outerWidth(0).appendTo($(obj));animo.find(".hlast").removeClass("hlast lar-main-color lar-highlight").addClass("lar-acc-icon lws-icon lws-icon-circle-down").html("")})},_enterTitle:function(event){var conteneur=$(event.currentTarget);var stdTitle=conteneur.find(".lar_overflow");var animTitle=conteneur.find(".lar_aoverflow");var theWidth=stdTitle.outerWidth();animTitle.stop(true,false).queue(function(next){$(this).css("display","flex");next()}).animate({width:theWidth},250)},_leaveTitle:function(event){var conteneur=$(event.currentTarget);var animTitle=conteneur.find(".lar_aoverflow");animTitle.stop(true,false).animate({width:"0px"},250).queue(function(next){$(this).css("display","none");next()})},_showContent:function(event){var conteneur=$(event.currentTarget);conteneur.closest(".lar-accordeon-item").find(".lar-accordeon-not-expanded-cont").hide();conteneur.closest(".lar-accordeon-item").find(".lar-accordeon-expanded-cont").show();var userInfo=conteneur.closest(".lar-accordeon-item").find(".lar-lsov-det-body");var lsInfo=conteneur.closest(".lar-accordeon-item").find(".lar-lsov-det-bodyr");if(userInfo.outerHeight()){if(userInfo.outerHeight()>lsInfo.outerHeight())lsInfo.outerHeight(userInfo.outerHeight())}},_hideContent:function(event){var conteneur=$(event.currentTarget);conteneur.closest(".lar-accordeon-item").find(".lar-accordeon-not-expanded-cont").show();conteneur.closest(".lar-accordeon-item").find(".lar-accordeon-expanded-cont").hide()},_clickUnlock:function(event){var conteneur=$(event.currentTarget).closest(".lar-unlockable");var lsys=conteneur.data("lsys");var cpoints=conteneur.data("cpoints");var cost=conteneur.data("cost");var reste=cpoints-cost;this._resetDivs();conteneur.find(".lar-unlockable-unlock-line").hide();conteneur.find(".lar-unlockable-confirm-line").css("display","flex");conteneur.siblings().each(function(){if($(this).data("lsys")==lsys){if($(this).data("cost")>reste){$(this).find(".lar-unlockable-not").show()}}})},_resetDivs:function(){var liste=this.element.find(".lar_unlockables_list");liste.find(".lar-unlockable-not").hide();liste.find(".lar-unlockable-confirm-line").hide();liste.find(".lar-unlockable-unlock-line").css("display","flex")},_clickNo:function(event){this._resetDivs()}})})(jQuery);jQuery(function($){$(".lar_main_container").lar_rewards()});
