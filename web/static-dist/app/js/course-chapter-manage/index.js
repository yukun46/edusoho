webpackJsonp(["app/js/course-chapter-manage/index"],[function(a,e,t){"use strict";function s(a){return a&&a.__esModule?a:{"default":a}}var n=t("b334fd7e4c5a19234db2"),r=s(n),i=function(a){var e=a.sortable("serialize").get();$.post(a.data("sortUrl"),{ids:e},function(e){var t=0,s=0,n=0;a.find(".task-manage-unit, .task-manage-chapter").each(function(){var a=$(this);a.hasClass("item-lesson")?(t++,a.find(".number").text(t)):a.hasClass("task-manage-unit")?(n++,a.find(".number").text(n)):a.hasClass("task-manage-chapter")&&(s++,n=0,a.find(".number").text(s))})})};$("#course-chapter-btn").on("click",function(){var a=$(this),e=$("#course-chapter-form");e.validate({rules:{title:"required"},ajax:!0,currentDom:a,submitSuccess:function(t){a.closest(".modal").modal("hide");var s=$("#"+$(t).attr("id"));if(s.length)s.replaceWith(t),(0,r.default)("success",Translator.trans("信息已保存"));else{var n=$("#"+e.data("parentid"));if(n.length){var l=0;n.nextAll().each(function(){if($(this).hasClass("task-manage-chapter"))return $(this).before(t),l=1,!1}),1!=l&&$("#sortable-list").append(t)}else $("#sortable-list").append(t);var c=$("#sortable-list");i(c)}}})})}]);