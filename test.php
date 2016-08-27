<?php

?>
<html>
<head>
    <meta charset="utf-8">
    <title>FreeSwitch XML Curl/Cdr test page</title>
    <style>
        fieldset {
            width: 50%;
        }
        input, textarea, select {
            display: block;
            margin-bottom: 10px;
            width: 50%;
        }

        .dialplan, .chatplan {
            display: none;
        }
    </style>
</head>
<body>
<div style="display: flex">
    <fieldset>
        <legend>XML Curl</legend>
        <form method="post" action="/index.php" id="curl-form">
            <select name="section" id="section">
                <option value="directory" selected>directory</option>
                <option value="dialplan">dialplan</option>
                <option value="chatplan">chatplan</option>
                <option value="configuration">configuration</option>
            </select>
            <input name="domain" type="text" class="directory field" placeholder="domain" required>
            <input name="user" type="text" class="directory field" placeholder="user">
            <input name="key" type="text" class="directory field" placeholder="key" value="id" required>
            <input name="group" type="text" class="directory field" placeholder="group">
            <input name="Hunt-Context" type="text" class="dialplan field" placeholder="context" disabled required>
            <input name="context" type="text" class="chatplan field" placeholder="context" disabled required>
            <button type="submit">Send</button>
        </form>
    </fieldset>
    <fieldset>
        <legend>XML Cdr</legend>
        <form method="post" action="/index.php">
            <input name="uuid" type="text" placeholder="uuid">
            <textarea name="cdr" placeholder="cdr xml" rows="10"></textarea>
            <button type="submit">Send</button>
        </form>
    </fieldset>
</div>
<script>

    document.getElementById('section').onchange = function () {
        Array.prototype.forEach.call(document.getElementsByClassName('field'), function (input) {
            input.style.display = 'none';
            input.disabled = true;
        });
        Array.prototype.forEach.call(document.getElementsByClassName(this.value), function (input) {
            input.style.display = 'block';
            input.disabled = false;
        });
    };

    document.getElementById('curl-form').onsubmit = function () {
        Array.prototype.forEach.call(this.getElementsByTagName('input'), function (input) {
            if (input.value == '') {
                input.disabled = true;
            }
        });
    };

</script>
</body>
</html>
