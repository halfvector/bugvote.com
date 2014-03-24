$(document).ready(function() {

    // enable chosen
    $('[data-rel="chosen"],[rel="chosen"]').chosen();
    //$(".chzn-select").chosen();

    $(".chzn-select").chosen({
        create_option: function(term) {
            this.append_option({
                value: term,
                text: term
            });
            $('#myModal').modal({});
        },
        persistent_create_option: true,
        create_option_text: 'Create New Business'
    });

    $('[rel=tooltip]').tooltip();
    $('time.date-and-minutes').localize('mmm, d yyyy h:MM a');
    $('time.date-only').localize('mmm, d yyyy');
    $('time.date-and-hour').localize('mmm, d yyyy h a');

    $("time.timeago").timeago();

    //$(".dropdown-btn").autoform();
    $('[data-endpoint]').autoform();

    function pausecomp(millis)
    {
        var date = new Date();
        var curDate = null;
        do { curDate = new Date(); }
        while(curDate-date < millis);
    }

    // simulate delay to see how it affects page load
    //console.log("sleeping..");
    //pausecomp(1000);
    //console.log("sleeping.. done");

});

(function($) {

    $.fn.autoform = function() {
        this.each(function() {

            var $this = $(this);

            var csrf = $this.data('csrf');
            var postUrl = $this.data('endpoint');
            var valueTargetId = $this.data('value-target-id');
            var valueTarget = $this.data('value-target');

            var entries = $this.find("[data-push]");

            if(!postUrl || !entries.length)
                return;

            console.log("Bugvote::Autoform(); post = " + postUrl);

            entries.bind("click", function(){
                //var value = $(this).data();

                var name = $(this).data("name");
                var value = $(this).data("value");
                var target = $(this);

                console.log("autoform;  sending: " + name + " = " + value);

                var postData = {
                    'csrf': csrf
                };

                postData[name] = value;

                $.post(postUrl, postData, null, "json")
                    .done(function(result) {
                        console.log("Bugvote::Autoform(); got results:");
                        console.dir(result);

                        if(valueTargetId)
                            $("#" + valueTargetId).text(result.value);

                        target.data("latest-value", result.value);
                    })
                    .fail(function(result) {
                        console.log("Bugvote::Autoform(); got error:");
                        console.dir(result.responseText);
                    })
                ;
            });
        });
    }

}(jQuery));

(function($, window, document, undefined) {
    'use strict';

    if(!$.fn.lexy)
        $.fn.lexy = {};

    function Dropdown(target, options) {
        var element = $(target);
        var overlay = null;

        this.init = function() {
            var that = this;

            element.on('click.lexy.dropdown', function(e) {
                e.stopPropagation();
                that.toggle();
            });

            //this.toggle();
        }

        this.toggle = function() {
            if(element.data("dropdown-state") === "open")
            {
                this.hideMenu();
            } else
            {
                this.showMenu();
            }
        }

        this.hideMenu = function() {
            element.data("dropdown-state", "closed");
            element.find(".dropdown-content").fadeOut(150);
            overlay.fadeOut(150);
        }

        this.showMenu = function() {
            element.data("dropdown-state", "open");
            element.find(".dropdown-content").fadeIn(100);

            if(!overlay) {
                // create the overlay for the first time
                overlay = $('<div class="capture-overlay"></div>');
                element.append(overlay);
            }

            overlay.fadeIn(100);
        }

        this.init();
    }

    $.fn.dropdown = function(options) {
        options = $.extend({}, $.fn.dropdown.options, options);

        return this.each(function() {
            // only instantiate once
            if(!$.data(this, "plugin_lexy_dropdown"))
                $.data(this, "plugin_lexy_dropdown", new Dropdown(this, options));
        });
    };

    $.fn.dropdown.options = {
        className: "m-dropdown",
        dropdownElement: "dropdown-content"
    };

    /*
     // hook to close dropdowns on an outside click event
     $(document).on('click.lexy.dropdown', ".dropdown-trigger", function(e) {
     $(e.currentTarget).lexy.dropdown();
     });
     */

    $(".m-dropdown").dropdown();

}(jQuery));

(function($, window, document, undefined) {
    'use strict';

    function BugDetailsVoter(target) {
        var $element = $(target);
        var $upvote = $element.find('.up-vote');
        var $downvote = $element.find('.down-vote');

        var overlay = null;
        var that = this;
        var voteUrl = $element.data("endpoint");
        var csrf = $element.data("csrf");

        var voteScore = parseInt($('#bug-vote-count').text());

        console.log("Bugvote::BugDetailsVoter(); vote url: " + voteUrl);

        function toggleUpvote(e) {
            // [0,1]
            var isUpvoted = parseInt($(this).data('value'));
            // [0,1]
            var newValue = 1 - isUpvoted;
            // [-1,1]
            var scoreAdjust = newValue * 2 - 1;

            voteScore += scoreAdjust;

            if($downvote.data('value')) {
                voteScore ++;
                $downvote.removeClass("voted");
                $downvote.data('value', 0);
            }

            if(newValue)
                $(this).addClass("voted");
            else
                $(this).removeClass("voted");

            $('#bug-vote-count').text(voteScore);
            $upvote.data('value', newValue);
            $upvote.attr('title',$upvote.data('title-' + newValue));

            that.vote(newValue);
        }

        function toggleDownvote(e) {
            // [0,1]
            var isDownvoted = parseInt($(this).data('value'));
            // [0,1]
            var newValue = 1 - isDownvoted;
            // [-1,1]
            var scoreAdjust = isDownvoted * 2 - 1;

            voteScore += scoreAdjust;

            if($upvote.data('value')) {
                voteScore --;
                $upvote.removeClass("voted");
                $upvote.data('value', 0);
            }

            if(newValue)
                $(this).addClass("voted");
            else
                $(this).removeClass("voted");

            $('#bug-vote-count').text(voteScore);
            $downvote.data('value', newValue);
            $downvote.attr('title',$downvote.data('title-' + newValue));

            that.vote(-newValue);
        }

        this.vote = function(encodedVote) {
            var postData = {
                'csrf': csrf,
                'idea': $element.data('bugid'),
                'vote': encodedVote
            };

            console.dir(postData);

            $.post(voteUrl, postData, null, 'json')
                .done(function(result) {
                    console.log("Bugvote::BugDetailsVoter(); result: ");
                    console.dir(result);
                })
            ;
        }

        // init
        $upvote.on('click.lexy.upvote', toggleUpvote);
        $downvote.on('click.lexy.downvote', toggleDownvote);
    }

    new BugDetailsVoter($("#bug-details-votebox"));

}(jQuery));

(function($, window, document, undefined) {
    'use strict';

    if(!$.fn.lexy)
        $.fn.lexy = {};

    function BuglistItemUpvote(target, options) {
        var element = $(target);
        var overlay = null;
        var $votebox = $(target);
        var that = this;

        var vote = parseInt($votebox.data("vote"));
        var score = parseInt($votebox.find(".vote-counter").text());

        //console.log("current vote: " + vote);
        //console.log("current score: " + score);

        if(vote == 1)
            $votebox.find(".up-vote").addClass("voted");
        else if(vote == -1)
            $votebox.find(".down-vote").addClass("voted");

        that.placeVote = function(encodedVote) {
            var csrf = $votebox.data("csrf");
            var bugid = $votebox.data("bugid");
            var voteUrl = $votebox.data("endpoint");

            var postData = {
                'csrf': csrf,
                'idea': bugid,
                'vote': encodedVote
            };

            $.post(voteUrl, postData, null, 'json')
                .done(function(result) {
                    console.log("BuglistItemUpvote::placeVote(); result: ");
                    console.dir(result);
                }
            )
            ;
        }

        this.reset = function() {
            if(vote > 0 ) {
                // already upvoted, toggle it off
                score --;
                $votebox.find(".up-vote").removeClass("voted");
            } else if(vote < 0) {
                // already downvoted, turn off downvote
                score ++;
                $votebox.find(".down-vote").removeClass("voted");
            }
        }

        this.prepVote = function(newVote) {
            var newScore = score + newVote;

            // save changes locally
            vote = newVote;
            score = newScore;

            that.placeVote(newVote);

            $votebox.find(".vote-counter").text(newScore);
        }

        this.upvote = function() {
            that.reset();

            if(!vote)
                $votebox.find(".up-vote").addClass("voted");

            that.prepVote(vote == 1 ? 0 : 1);
        }

        this.downvote = function() {
            that.reset();

            if(!vote)
                $votebox.find(".down-vote").addClass("voted");

            that.prepVote(vote == -1 ? 0 : -1);
        }

        $votebox.find(".up-vote").on('click.lexy.buglistitemvoter', this.upvote);
        $votebox.find(".down-vote").on('click.lexy.buglistitemvoter', this.downvote);

    }

    $.fn.lexy_buglistitemvoter = function(options) {
        options = $.extend({}, $.fn.dropdown.options, options);

        return this.each(function() {
            // only instantiate once
            if(!$.data(this, "plugin_lexy_buglistitemvoter"))
                $.data(this, "plugin_lexy_buglistitemvoter", new BuglistItemUpvote(this, options));
        });
    };

    $.fn.lexy_buglistitemvoter.options = {
        className: "m-dropdown",
        dropdownElement: "dropdown-content"
    };

    $(".m-buglist-item-votebox-simple").lexy_buglistitemvoter();

}(jQuery));

$("#topnav-expander-button").on('click.lexy', function(){
    $("#topnav-primary-menu").toggleClass("visible");
    $("#topnav-expander-container").toggleClass("toggled");
});

String.prototype.templateFormat = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
        return typeof args[number] != 'undefined'
            ? args[number]
            : match
            ;
    });
};

$("[data-reply-to]").on('click.lexy', function() {
    var data = [];
    var parentCommentId = $(this).data("reply-to");
    var parentComment = $(this).closest(".m-comment-with-upvote");
    var template = $("#ReplyComment").html();
    parentComment.append(template.templateFormat(parentCommentId));
});

$('[data-dismiss="attachment"]').on('click.lexy', function(e) {
    // remove parent
    $(this).closest('[data-provides="attachment"]').remove();
});