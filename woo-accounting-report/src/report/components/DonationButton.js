import React, { useEffect } from '@wordpress/element';

const DonateButton = () => {
    useEffect(() => {
        const script = document.createElement('script');
        script.src = "https://www.paypalobjects.com/donate/sdk/donate-sdk.js";
        script.onload = () => {
            window.PayPal.Donation.Button({
                env: 'production',
                hosted_button_id: '6WNTXW9KQN736',
                image: {
                    src: 'https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif',
                    alt: 'Donate with PayPal button',
                    title: 'PayPal - The safer, easier way to pay online!',
                }
            }).render('#donate-button');
        };
        document.body.appendChild(script);
        return () => {
            document.body.removeChild(script);
        };
    }, []);

    return (
        <div id="donate-button-container">
            <div id="donate-button"></div>
        </div>
    );
};

export default DonateButton;