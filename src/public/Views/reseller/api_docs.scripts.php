<script>
	$(document).ready(function() {
		setTimeout(pingSession, 30000);
		refreshTooltips();
		$(".copy-btn").click(function() {
			var rText = $(this).data("copy");
			var rBtn = $(this);
			if (navigator.clipboard) {
				navigator.clipboard.writeText(rText).then(function() {
					rBtn.addClass("btn-success");
					setTimeout(function() { rBtn.removeClass("btn-success"); }, 800);
				});
			} else {
				var rInput = $("<textarea>").val(rText).appendTo("body").select();
				document.execCommand("copy");
				rInput.remove();
				rBtn.addClass("btn-success");
				setTimeout(function() { rBtn.removeClass("btn-success"); }, 800);
			}
		});
	});
</script>
