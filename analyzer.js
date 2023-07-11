function ErrorTracker(analysisResult) {
  const data = analysisResult;

  // Error Check
  titleCheck(data?.title);
  descriptionCheck(data?.description);
}

// Count
let highIssues = null;
let mediumIssues = null;
let lowIssues = null;
let testPassed = null;

// Icon classes
const successIcon = ["an", "an-chack", "seo-icon", "seo-icon-success"];
const dangerIcon = ["an", "an-triangle", "seo-icon", "seo-icon-danger"];
const warningIcon = ["an", "an-square", "seo-icon", "seo-icon-warning"];
const infoIcon = ["an", "an-circle", "seo-icon", "seo-icon-info"];

// Alert classes
const successAlert = ["alert", "alert-success"];
const dangerAlert = ["alert", "alert-danger"];
const warningAlert = ["alert", "alert-warning"];
const infoAlert = ["alert", "alert-info"];

function titleCheck(title) {
  const container = document.querySelector("[csi-title]");
  const infoText = container.querySelector("[info-message]");
  const icon = container.querySelector("[info-icon]");
  const box = container.querySelector("[info-box]");
  const tLength = container.querySelector("[info-length]");

  const successCondition = title.length >= 45 && title.length <= 60;
  const warningCondition = title.length < 45 && title.length > 25;

  tLength.innerHTML = `${title.length} Characters`;
  icon.className = "";
  box.className = "";

  if (successCondition) {
    icon.classList.add(...successIcon);
    box.classList.add(...successAlert);
    infoText.innerHTML = "The webpage is using a good title tag.";
    testPassed++;
  } else if (warningCondition) {
    icon.classList.add(...warningIcon);
    box.classList.add(...warningAlert);
    infoText.innerHTML = `The webpage is using a title tag with a length of <strong>${title.length} characters.</strong> We recommend using a title with a length between <strong>45 - 60 characters</strong> in order to fit Google Search results that have a 600-pixel limit.`;
    mediumIssues++;
  } else if (!title) {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `This <strong>webpage lacks a title tag!</strong> Including a title tag is crucial as it provides a concise overview of the page for search engines.`;
    highIssues++;
  } else if (title.length > successCondition) {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `The webpage is using a title tag with a length of <strong>${title.length} characters.</strong> It is recommended to use well-written and engaging titles with a length between <strong>45 - 60 characters</strong>.`;
    highIssues++;
  } else {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `The webpage is using a title tag with a length of <strong>${title.length} characters.</strong> Please review the title length for optimal results.`;
    highIssues++;
  }
}

function descriptionCheck(description) {
  const container = document.querySelector("[csi-description]");
  const infoText = container.querySelector("[info-message]");
  const icon = container.querySelector("[info-icon]");
  const box = container.querySelector("[info-box]");
  const tLength = container.querySelector("[info-length]");

  const successCondition =
    description.length >= 120 && description.length <= 160;
  const warningCondition = description.length < 120 && description.length > 80;

  tLength.innerHTML = `${description.length} Characters`;
  icon.className = "";
  box.className = "";

  if (successCondition) {
    icon.classList.add(...successIcon);
    box.classList.add(...successAlert);
    infoText.innerHTML = "This webpage has a good meta description.";
    testPassed++;
  } else if (warningCondition) {
    icon.classList.add(...warningIcon);
    box.classList.add(...warningAlert);
    infoText.innerHTML = `This webpage has a meta description tag that is <strong>${description.length} characters long.</strong> It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces).</strong>`;
    mediumIssues++;
  } else if (!description) {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `This page is <strong>lacking a meta description tag!</strong> This element is required to provide a brief overview of the page to search engines.`;
    highIssues++;
  } else if (description.length > successCondition) {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `The webpage has a meta description tag with a length of <strong>${description.length} characters.</strong> It is recommended to use well-written and engaging meta descriptions with a length between <strong>80 and 160 characters (including spaces)</strong>.`;
    highIssues++;
  } else {
    icon.classList.add(...dangerIcon);
    box.classList.add(...dangerAlert);
    infoText.innerHTML = `The webpage has a meta description tag with a length of <strong>${description.length} characters.</strong> Please review the description length for optimal results.`;
    highIssues++;
  }
}
