let sideBarOpen= false;
let menuIcon= document.querySelector('.sidebar');

function openSideBar(){
    if(!sideBarOpen){
        menuIcon.classList.add('sidebar-responsive')
        sideBarOpen= true;
    }
}
function closeSideBar(){
    if(sideBarOpen){
        menuIcon.classList.remove('sidebar-responsive')
        sideBarOpen= false;
    }
}
function confirmLogout(event) {
    event.preventDefault(); 
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = event.target.href; 
    }
}
$(document).ready(function() {
    $('#user_table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "action.php",
            "type": "POST",
            "data": function(d) {
                d.action = 'fetch_user'; // Pass additional parameters if needed
            }
        },
        "pageLength": 15, // Number of entries per page
        "lengthMenu": [15, 25, 50, 100], // Options for entries per page
        "order": [], // Initial order
        "language": {
            "search": "Search:", // Custom text for search box
            "paginate": {
                "previous": "Previous",
                "next": "Next",
                "first": "First",
                "last": "Last"
            },
            "lengthMenu": "Show _MENU_ entries", // Custom text for length menu
            "info": "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
    function delete_data(id, status) {
    let new_status = 'Enable';
    
    if (status == 'Enable') {
        new_status = 'Disable';
    }
    
    if (confirm('Are you sure you want to ' + new_status + ' this user?')) {
        window.location.href = 'studententry.php?action=delete&id=' + id + '&status=' + new_status;
    }
}
});