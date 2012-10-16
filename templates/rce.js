$(function() {
	$("#iosRoundcubeEmbedIframeContainer").css({
		"width": $("#iosRoundcubeEmbedIframeContainer").width()
	});
	
	$("#iosRoundcubeEmbedIframeContainer").resizable();
	
	$("#iosRoundcubeEmbedIframeContainer iframe").css({
		"width": "100%",
		"height": "100%"
	});
});