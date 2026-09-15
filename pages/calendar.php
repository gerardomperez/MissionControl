<?php /* Calendar — Scheduled Tasks */ ?>

<div class="cal-wrapper">

    <!-- Page Header -->
    <div class="cal-header">
        <div class="cal-header-left">
            <div>
                <h1>Scheduled Tasks</h1>
                <p class="cal-date-range" id="calDateRange"></p>
            </div>
            <div class="cal-nav">
                <button class="btn btn-outline cal-nav-btn" id="calPrev" title="Previous week">&#8592;</button>
                <button class="btn btn-outline cal-nav-btn" id="calToday">Today</button>
                <button class="btn btn-outline cal-nav-btn" id="calNext" title="Next week">&#8594;</button>
            </div>
        </div>
        <div class="cal-legend">
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--success)"></span>Success</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--danger)"></span>Failed</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--pending)"></span>Pending</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:var(--warning)"></span>Overdue</span>
        </div>
    </div>

    <!-- Day-of-week column headers -->
    <div class="cal-dow-row">
        <div class="cal-dow">Mon</div>
        <div class="cal-dow">Tue</div>
        <div class="cal-dow">Wed</div>
        <div class="cal-dow">Thu</div>
        <div class="cal-dow">Fri</div>
        <div class="cal-dow">Sat</div>
        <div class="cal-dow">Sun</div>
    </div>

    <!-- Grid -->
    <div class="cal-grid" id="calGrid"></div>

</div>
