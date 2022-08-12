var API = 'lib/router.php';

/**
 * Saves a new paste
 */
function save() {
	var $textarea = $('#newpaste');
	var $name = $('#name');
	var $email = $('#email');
	var content = $textarea.val();
	var emailAddress = $email.val();
	var realName = $name.val();
	if (!content.length || !emailAddress.length || !realName.length) return;

	Cookies.set('user', realName, { expires: 365 });
	Cookies.set('email', emailAddress, { expires: 365 });

	$.post(
		API,
		{
			do: 'save',
			'content': content,
			'email': emailAddress,
			'name': realName
		},
		function (paste) {
			if (paste === false) {
				alert('something went wrong');
				return;
			}
			$textarea.val('');
			location = location.pathname + '#' + paste.uid;
			location.reload()
		},
		'json'
	)
}

/**
 * loads as paste from backend
 *
 * @param {String} uid
 */
function load(uid) {
	$.get(
		API,
		{
			do: 'load',
			uid: uid
		},
		function (paste) {
			var dateTime = new Date(paste.time * 1000);
			var safePaste = escapeHtml(paste.content);
			$('#author').text(paste.name + ' pasted this on ' + dateTime.toLocaleString())
			$('#paste').html(PR.prettyPrintOne(safePaste, null, true)).addClass('prettyprint');
			$('#newpaste').html(paste.content);
			loadComments(uid);
			$('#name').val(Cookies.get('user'));
			$('#email').val(Cookies.get('email'));
		},
		'json'
	);
}

/**
 * Loads all current comments for the paste
 *
 * @param {String} uid
 */
function loadComments(uid) {
	$.get(
		API,
		{
			do: 'loadComments',
			uid: uid
		},
		function (comments) {
			var $lines = $('#paste').find('li');

			for (var i = 0; i < comments.length; i++) {
				commentShow($($lines.get(comments[i].line)), comments[i]);
			}

			$lines.click(function (e) {
				console.log('hi');
				if (e.target != this) return;
				commentForm(uid, $(this));
				e.preventDefault();
				e.stopPropagation();
			});

			$('#help').show();
		},
		'json'
	)
}

/**
 * Shows the given comment at the given line
 *
 * Makes sure the comment is shown before a possible comment edit form
 *
 * @param {jQuery} $li The line element
 * @param {Object} comment The comment text
 */
function commentShow($li, comment) {
	var $last = $li.children().last();

	var $comment = $('<div class="comment" style="border-color: #' + comment.color + '">' +
		'<div class="text"></div>' +
		'<div class="user"></div>' +
		'</div>');
		console.log(comment);
	var dateTime = new Date(comment.time * 1000);
	$comment.find('.text').html(comment.comment);
	$comment.find('.user').text(dateTime.toLocaleString() + ' - ' + comment.user_name);

	if ($last.hasClass('newcomment')) {
		$last.before($comment);
	} else {
		$last.after($comment);
	}
}

/**
 * Saves the comment that has been entered in $form
 *
 * @param {String} uid
 * @param {jQuery} $form
 */
function commentSave(uid, $form) {
	var $txtarea = $form.find('textarea');
	var comment = $txtarea.val();
	if (!comment.length) return;

	var user = $.trim($form.find('input[name="user"]').val());
	var email = $.trim($form.find('input[name="email"]').val());
	if (user == '') {
		alert('Please enter a name to have your comments properly attributed');
		return;
	}
	if (email == '') {
		alert('Please enter a name to have your comments properly attributed');
		return;
	}

	$form.toggle();
	Cookies.set('user', user, { expires: 365 });
	Cookies.set('email', email, { expires: 365 });

	$.post(
		API,
		{
			do: 'saveComment',
			uid: uid,
			comment: comment,
			line: $form.parent().index(),
			user: user,
			email: email
		},
		function (comment) {
			if (!comment) {
				alert('Something went wrong');
				return;
			}
			$txtarea.val('');
			commentShow($($form.parent()), comment);
		},
		'json'
	)
}

/**
 * Toggle comment form showing for the given line element
 *
 * @param {String} uid
 * @param {jQuery} $li
 */
function commentForm(uid, $li) {
	var $form = $li.find('.newcomment');
	if (!$form.length) {
		$form = $('<div class="newcomment">' +
			'<textarea></textarea><br>' +
			'<label>Your Name: <input type="text" name="user"></label>' +
			'<label>Your Email: <input type="email" name="email"></label>' +
			'<button>Save</button>' +
			'</div>');
		$form.find('button').click(function (e) {
			commentSave(uid, $form);
			e.preventDefault();
			e.stopPropagation();
		});

		$li.append($form);
	} else {
		$form.toggle();
	}

	$form.find('input[name="user"]').val(Cookies.get('user'));
	$form.find('input[name="email"]').val(Cookies.get('email'));
}

function escapeHtml(text) {
	var map = {
	  '&': '&amp;',
	  '<': '&lt;',
	  '>': '&gt;',
	  '"': '&quot;',
	  "'": '&#039;'
	};
	
	return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }


/**
 * Main
 */
$(function () {
	var $new = $('#new');
	$new.find('button').click(function (e) {
		save();
		e.preventDefault();
		e.stopPropagation();
	});
	$new.find('textarea').focus(function (e) {
		$(this).animate({height: '35em'}, 'fast');
	});


	if (location.hash.length) {
		load(location.hash.substr(1));
	}
});