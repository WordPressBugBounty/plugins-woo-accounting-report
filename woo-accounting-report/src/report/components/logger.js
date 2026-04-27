import getSetting from '../hooks/wooCommerceOptions';

const debug = false;
const Logger = async (function_name, message, isJson = false) => {

    if (!debug) {
        const logging = await getSetting('bjorntech_wcar_logging');

        if (logging !== 'yes') return;

        const nonce = await getSetting('bjorntech_wcar_nonce');

        if (!nonce) return;

        fetch('/wp-json/bjorntech-accounting/v1/log', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ message, function: function_name, is_json: isJson }),
        });
    } else {
        console.log(function_name, message);
    }

};



export default Logger;
