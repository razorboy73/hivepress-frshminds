jQuery(function($){$(document).on("click",".lws-reward-redeem",function(event){var link=$(event.target);if(!link.prop("disabled")){$(".lws-reward-redeem, .lws-woorewards-reward-claim-other-button").prop("disabled",true);window.location=link.data("href")}return false})});
