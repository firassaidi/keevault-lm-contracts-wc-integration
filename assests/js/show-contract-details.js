jQuery(document).ready(function($) {
    const container = $('#contract-details-container');

    // Make AJAX request to fetch contract details
    $.post(contractDetailsData.ajax_url, {
        action: 'get_contract_details',
        order_id: contractDetailsData.order_id
    })
        .done(function(response) {
            if (response.success) {
                // Start building the HTML table with WooCommerce styling
                let contractHtml = `
                <table class="shop_table woocommerce-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: left;">Name</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: left;">Contract Key</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: center;">License Keys Quantity</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody class="woocommerce-table__body">`;

                // Add a table row for each contract
                response.data.forEach(contract => {
                    contractHtml += `
                    <tr class="woocommerce-table__row">
                        <td class="woocommerce-table__cell" style="padding: 12px;">${contract.name}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px;">${contract.contract_key}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px; text-align: center;">${contract.license_keys_quantity}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px; text-align: center; text-transform: capitalize">${contract.status}</td>
                    </tr>`;
                });

                // Close the table
                contractHtml += `
                    </tbody>
                </table>`;

                // Insert the table into the container
                container.html(contractHtml);
            } else {
                container.html('<p>No contract details available for this order.</p>');
            }
        })
        .fail(function() {
            container.html('<p>Failed to load contract details. Please try again later.</p>');
        });
});
