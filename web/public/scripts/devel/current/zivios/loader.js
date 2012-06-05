var js_clock = function() {
    var clock_time = new Date();
    var clock_hours = clock_time.getHours();
    var clock_minutes = clock_time.getMinutes();
    var clock_seconds = clock_time.getSeconds();
    var clock_suffix = "AM";

    if (clock_hours > 11) {
        clock_suffix = "PM";
        clock_hours = clock_hours - 12;
    }

    if (clock_hours == 0)
        clock_hours = 12;

    if (clock_hours < 10)
        clock_hours = "0" + clock_hours;

    if (clock_minutes < 10)
        clock_minutes = "0" + clock_minutes;

    if (clock_seconds < 10)
        clock_seconds = "0" + clock_seconds;

    var clock_div = document.getElementById('js_clock');
        clock_div.innerHTML = clock_hours + ":" + clock_minutes + ":" +
        clock_seconds + " " + clock_suffix;

    setTimeout("js_clock()", 1000);
}

