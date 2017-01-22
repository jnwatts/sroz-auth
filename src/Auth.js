$(function() {
    window.auth = {
        uri: "",
        redirect: "",
        register: function() {
            auth.msg("Registering...");
            console.log("Register request");
            $.ajax({
                type: "POST",
                url: auth.uri,
                data: {u2f: "register"},
                dataType: "json"
            }).done(auth.register_callback);
        },
        register_callback: function(data) {
            console.log("Register response", data);
            auth.msg("Press U2F button now to register...");
            var request = data.reg;
            var appId = request.appId;
            var registerRequests = [{version: request.version, challenge: request.challenge}];
            u2f.register(appId, registerRequests, data.sign, function(data) {
                console.log("Register2 request", data);
                if (data.errorCode) {
                    auth.msg("Failed to register: " + data.errorCode);
                    return false;
                }
                auth.msg("Registering...");
                $.ajax({
                    type: "POST",
                    url: auth.uri,
                    data: {u2f: "register2", reg: JSON.stringify(request), response: JSON.stringify(data)},
                    dataType: "json"
                }).done(auth.register2_callback);
            });
        },
        register2_callback: function(data) {
            console.log("Register2 response", data);
            auth.msg("");
            auth.update_keys(data);
            auth.authenticate();
        },
        unregister: function(keyHandle) {
            $.ajax({
                type: "POST",
                url: auth.uri,
                data: {u2f: "unregister", keyHandle: keyHandle},
                dataType: "json"
            }).done(auth.update_keys);
        },
        update_keys: function(keys) {
            if (keys)
                auth.keys = keys;
            var obj = $('#keys');
            obj.html("");
            auth.keys.forEach(function (key, i) {
                var k = $("<input type=\"button\">");
                if (key.valid)
                    k.prop("value", "Unregister #" + i);
                else
                    k.prop("value", "Unregister #" + i + " (not yet authenticated)");
                k.on('click', function() {
                    auth.unregister(key.keyHandle);
                });
                obj.append(k);
                obj.append($('<br>'));
            });
        },
        authenticate: function() {
            console.log("Authenticate request");
            $.ajax({
                type: "POST",
                url: auth.uri,
                data: {u2f: "authenticate"},
                dataType: "json"
            }).done(auth.authenticate_callback);
        },
        authenticate_callback: function(data) {
            console.log("Authenticate challenge", data);
            auth.msg("Press U2F button now to authenticate...");
            var request = data[0];
            var appId = request.appId;
            var challenge = request.challenge;
            var registeredKeys = [{version: request.version, keyHandle: request.keyHandle}];
            u2f.sign(appId, challenge, registeredKeys, function(data) {
                console.log("Auth callback", data);
                if (data.errorCode) {
                    auth.msg("Failed to sign: " + data.errorCode);
                    return false;
                }
                auth.msg("Authenticating...");
                $.ajax({
                    type: "POST",
                    url: auth.uri,
                    data: {u2f: "authenticate2", auth: JSON.stringify(request), response: JSON.stringify(data)},
                    dataType: "json"
                }).done(auth.authenticate_callback2);
            });
        },
        authenticate_callback2: function(data) {
            console.log("Authenticate2 callback", data);
            auth.msg("");
            auth.update_keys(data);
            if (auth.redirect) {
                location.href = auth.redirect;
            }
        },
        msg: function(str) {
            $('#msg').html(str);
        },
        keys: [],
    };

    $('#register').on('click', function() {
        auth.register();
    });

    $('#authenticate').on('click', function() {
        auth.authenticate();
    });
});
