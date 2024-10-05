import React from 'react';
import DonationButton from './DonationButton';

const SupportPluginPrompt = () => {
    return (
        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', flexDirection: 'column', textAlign: 'center' }}>
            <div style={{ border: '1px solid lightgrey', backgroundColor: 'lightgrey', padding: '15px', borderRadius: '10px', boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)', minWidth: '300px', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                <p style={{ fontWeight: 'bold', marginBottom: '10px' }}>Support Our Free Plugin</p>
                <p>Love our Accounting Report plugin? Consider contributing to its development and support through a donation.</p>
                <DonationButton />
            </div>
        </div>
    );
}

export default SupportPluginPrompt;