import React from '@wordpress/element';

const BetaVersionBanner = () => {
    const adminUrl = 'admin.php?page=wc-reports&tab=accounting';
    const mailTo = 'hello@bjorntech.com';

    return (
        <div style={{ border: '2px solid #000', padding: '10px', margin: '10px 0', backgroundColor: '#D3D3D3', borderRadius: '10px' }}>
            <b>
                Welcome to our new Analytics section, this is
                a beta version and should be used with caution.
                You can find the old version <a href={adminUrl}>here</a>.
                Send us your thoughts and report any bugs to
                <a href={`mailto:${mailTo}`}> {mailTo} </a>
            </b>
        </div>
    );
}

export default BetaVersionBanner;