
/**
 * DELETE CONFIRMATIONS
 * Ask for confirmation before submitting delete forms.
 */

// Confirm event deletion
function confirmDeleteEvent() {
    return confirm(
        'Are you sure you want to delete this event? Its registrations will also be deleted.'
    );
}

// Confirm student registration removal
function confirmDeleteStudent() {
    return confirm(
        'Are you sure you want to remove this student registration?'
    );
}
