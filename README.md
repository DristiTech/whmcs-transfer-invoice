# WHMCS Transfer Invoice Addon

**WHMCS admin addon to safely transfer invoices and related transactions between clients.**

---

## Features

* Transfer an invoice from one client to another.
* Optionally move associated transactions to the new client.
* Admin note logging for each transfer.
* Client search with autocomplete (search by ID, name, or email).
* Multiple transaction selection with Select All / Unselect All functionality.
* Styled and scrollable transaction list.
* Activity log for audit purposes.

## Installation

1. Download or clone the repository.
2. Upload the `TransferInvoice` folder to your WHMCS installation under `modules/addons/`.
3. Go to WHMCS Admin → Setup → Addon Modules → Activate `Transfer Invoice`.
4. Configure if needed (no special settings required by default).

## Usage

1. Navigate to **Addon Modules → Transfer Invoice** in the WHMCS admin panel.
2. Enter the **Invoice ID** you want to transfer.
3. Search and select the **Target Client** using the autocomplete field.
4. Optionally select **transactions** to move to the new client.
5. Add an **admin note** if required.
6. Click **Transfer Invoice** to complete the operation.

## Requirements

* WHMCS 7.x or higher.
* PHP 7.4 or higher.
* Database backup recommended before using this addon.

## Screenshots

*(You can add screenshots here of the form, autocomplete, and transaction selection list.)*

## Safety Notes

* Always backup your database before performing any invoice transfer.
* Transferring invoices can have accounting or tax implications. Prefer using "Cancel & Recreate" for tax compliance if needed.
* Only accessible by admin users.

## License

MIT License — use at your own risk.

---

*Developed by Dristi*