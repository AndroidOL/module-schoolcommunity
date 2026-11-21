/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

window.schoolCommunityChooseRecipient = function (personID, label) {
    const recipientID = document.getElementById('recipientID');
    const recipientName = document.getElementById('recipientName');

    if (!recipientID || !recipientName) {
        return false;
    }

    recipientID.value = personID;
    recipientName.value = label;
    recipientID.dispatchEvent(new Event('change', { bubbles: true }));

    if (window.Alpine && typeof window.Alpine.$data === 'function') {
        const page = window.Alpine.$data(document.body);
        if (page && typeof page.modalOpen !== 'undefined') {
            page.modalOpen = false;
        }
    }

    return false;
};
