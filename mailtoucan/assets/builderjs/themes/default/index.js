// builder.widgetsBox.addWidget(new CheckoutWidget(), {
//     group: 'Basic',
// });
// builder.widgetsBox.addWidget(new CheckoutSimpleWidget(), {
//     group: 'Basic',
// });
builder.widgetsBox.addWidget(new ParagraphWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new HeadingWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new WelcomeWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new MenuWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new DividerWidget(), {
    group: 'Basic',
});
// builder.widgetsBox.addWidget(new ListWidget(), {
//     group: 'Basic',
// });
builder.widgetsBox.addWidget(new ImageWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new GridWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new ButtonWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new VideoWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new AlertWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new RSSWidget(), {
    group: 'Basic',
});
builder.widgetsBox.addWidget(new YoutubeWidget(), {
    group: 'Basic',
});

// Image & Text
builder.widgetsBox.addWidget(new ImageTextLeftWidget(), {
    group: 'Image & Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new ImageTextTopWidget(), {
    group: 'Image & Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new ImageTextRightWidget(), {
    group: 'Image & Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new ImageTextBottomWidget(), {
    group: 'Image & Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new ImageTextDoubleWidget(), {
    group: 'Image & Text',
    type: 'image'
});

// Text
builder.widgetsBox.addWidget(new TextCenterWidget(), {
    group: 'Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new TextLeftWidget(), {
    group: 'Text',
    type: 'image'
});
builder.widgetsBox.addWidget(new TextDoubleWidget(), {
    group: 'Text',
    type: 'image'
});

builder.widgetsBox.render();