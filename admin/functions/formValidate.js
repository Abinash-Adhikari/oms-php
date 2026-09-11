function createHiddenInput(form,name, value) {
    let input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = hex_sha512(value);
    form.appendChild(input);
};
function formhash2(form, opwdId, npwdId) {
    const opwd = document.getElementById(opwdId).value;
    const npwd = document.getElementById(npwdId).value;
    createHiddenInput(form,"old_pass", opwd);
    // Hash and store the new password
    createHiddenInput(form,"new_pass", npwd);
    // Clear the plaintext passwords
    opwd.value = "";
    npwd.value = "";

    return false;
}
