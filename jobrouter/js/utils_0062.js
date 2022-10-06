// utils build 0062

var document,
  dayjs,
  store,
  jr_get_value,
  jr_set_value,
  jr_get_subtable_value,
  jr_set_subtable_value,
  jr_loop_table,
  jr_get_subtable_row_ids,
  jr_sum_subtable_column,
  jr_show,
  jr_hide,
  jr_set_required,
  jr_set_disabled,
  jr_set_readonly,
  jr_show_subtable_column,
  jr_hide_subtable_column;

var $u = (function () {
  "use strict";

  function getFieldType(target) {
    if (isSubtableTarget(target)) {
      const targetAry = buildSubtableTargetAry(target);
      return store.field[targetAry[0]][targetAry[1]];
    } else return store.field[target];
  }

  function getValue(target) {
    const fieldType = getFieldType(target);
    let rawValue;

    if (isSubtableTarget(target)) {
      const targetAry = buildSubtableTargetAry(target, 3);
      rawValue = jr_get_subtable_value(
        targetAry[0],
        targetAry[2],
        targetAry[1]
      );
    } else rawValue = jr_get_value(target);

    if (rawValue) return castValueToType(rawValue, fieldType);
    else if (rawValue === 0 && fieldType === "number") return 0;
    else return "";
  }

  function getValueN(target) {
    const value = getValue(target);
    return value ? value : "";
  }

  function setValue(target, rawValue) {
    const data = {};
    data.target = target;
    data.rawValue = rawValue;
    data.fieldType = getFieldType(target);
    data.valueType = getType(rawValue);

    let logLevel = data.fieldType === data.valueType ? "DEBUG" : "WARN";

    data.value = data.fieldType === "dayjs" ? data.rawValue.$d : data.fieldType === data.valueType ? data.rawValue : "";

    log(logLevel, "$u.setValue", data);

    if (isSubtableTarget(data.target)) {
      const targetAry = buildSubtableTargetAry(data.target, 3);
      if (targetAry[2] === "*") {
        jr_loop_table(targetAry[0], function (subtable, rowId) {
          jr_set_subtable_value(subtable, rowId, targetAry[1], data.value);
        });
      } else
        jr_set_subtable_value(
          targetAry[0],
          targetAry[2],
          targetAry[1],
          data.value
        );
    } else jr_set_value(data.target, data.value);
  }
  function setValueN(target, rawValue) {
    if (rawValue) setValue(target, rawValue);
    else throw "value must not be empty.";
  }

  function fixFloat(rawValue) {
    return parseFloat(rawValue.toFixed(2));
  }

  function castValueToType(rawValue, type) {
    const castStringToGivenType = (rawValue, type) => {
      switch (type) {
        case "string":
          return String(rawValue);
        case "number":
          return parseFloat(rawValue);
        case "dayjs":
          return dayjs(rawValue);
        case "bool":
          return Boolean(rawValue);
        default:
          throw `unknown type ${type} given for value ${rawValue}`;
      }
    };

    let value;
    if (Array.isArray(rawValue)) {
      value = [];
      rawValue.forEach((item, index) => {
        value.push(castStringToGivenType(item, type));
      });
    } else value = castStringToGivenType(rawValue, type);

    return value;
  }

  function setFieldColor(target, color) {
    const exec = (myTarget) => {
      document
        .getElementById(myTarget)
        .setAttribute("style", "background-color:" + color);
    };

    if (isSubtableTarget(target)) {
      const targetAry = buildSubtableTargetAry(target, 3);

      if (targetAry[2] === "*") {
        jr_loop_table(targetAry[0], (subtable, row) => {
          exec(`${subtable}_${targetAry[1]}_${row}`);
        });
      } else exec(`${targetAry[0]}_${targetAry[1]}_${targetAry[2]}`);
    } else exec(target);
  }

  function setCellStyle(target, cellStyle) {}

  function getDate(
    value = "NOW",
    format = "YYYY-MM-DD HH:mm",
    strFormat = false
  ) {
    const myDate = value === "NOW" ? dayjs() : dayjs(value);
    return strFormat ? myDate.format(format) : myDate;
  }

  const propertyModifier = {
    show: (field, subtable) => {
      if (subtable) jr_show_subtable_column(subtable, field);
      else jr_show(field);
    },
    hide: (field, subtable) => {
      if (subtable) jr_hide_subtable_column(subtable, field);
      else jr_hide(field);
    },
    require: (field, subtable) => {
      if (subtable) stPropertyModifierLib.required(subtable, field, true);
      else jr_set_required(field, true);
    },
    unrequire: (field, subtable) => {
      if (subtable) stPropertyModifierLib.required(subtable, field, false);
      else jr_set_required(field, false);
    },
    enable: (field, subtable) => {
      if (subtable) stPropertyModifierLib.active(subtable, field, false);
      else jr_set_disabled(field, false);
    },
    disable: (field, subtable) => {
      if (subtable) stPropertyModifierLib.active(subtable, field, true);
      else jr_set_disabled(field, true);
    },
    empty: (field, subtable) => {
      if (subtable) setValue(`${subtable}.${field}.*`, "");
      else jr_set_value(field, "");
    },
    readonly: (field, subtable) => {
      if (subtable) stPropertyModifierLib.writeable(subtable, field, true);
      else jr_set_readonly(field, true);
    },
    readwrite: (field, subtable) => {
      if (subtable) stPropertyModifierLib.writeable(subtable, field, false);
      else jr_set_readonly(field, false);
    },
  };

  const stPropertyModifierLib = {
    active: (subtable, field, value) => {
      const rows = jr_get_subtable_row_ids(subtable);
      rows.forEach((row) => {
        document.getElementById(`${subtable}_${field}_${row}`).disabled = value;
      });
    },
    writeable: (subtable, field, value) => {
      const rows = jr_get_subtable_row_ids(subtable);
      rows.forEach((row) => {
        jr_set_readonly(`${subtable}_${field}_${row}`, value);
      });
    },
    required: (subtable, field, value) => {
      const rows = jr_get_subtable_row_ids(subtable);
      rows.forEach((row) => {
        const elem = document.getElementById(`${subtable}_${field}_${row}`);
        if (value) elem.classList.add("required");
        else elem.classList.remove("required");
      });
    },
  };

  function setProperty(prop, field, subtable) {
    if (subtable) setStProperty(prop, field, subtable);
    else propertyModifier[prop](field);
  }

  function setStProperty(prop, field, subtable) {
    if (getType(prop) === "array") {
      prop.forEach((propEntry) => {
        propertyModifier[propEntry](field, subtable);
      });
    } else propertyModifier[prop](field, subtable);
  }

  const processPropertiesType = {
    string: (properties, property) => { 
      setProperty(properties[property], property); 
    },
    array: (properties, property) => {
      for (let prop in properties[property]) {
        setProperty(prop, property);
      }
    },
    object: (properties, property) => {
      for (const field in properties[property]) {
        setProperty(properties[property][field], field, property);
      }
    }
  };

  function setProperties(properties) {
    for (const property in properties) {
      const type = getType(properties[property]);
      processPropertiesType[type](properties, property);
      // throw `Property of type ${type} can not be processed.`;
    }
  }

  function getType(value) {
    if (typeof value == "undefined" || value === null)
      return "null || undefined";

    const type = typeof value;
    if (type === "object") {
      if (value instanceof dayjs) return "dayjs";
      if (Array.isArray(value)) return "array";
    }
    return type;
  }

  function compareColumnSumToField(targetSt, targetField) {
    const targetAry = buildSubtableTargetAry(targetSt, 2);
    const val1 = getValue(targetField, "f");
    const val2 = jr_sum_subtable_column(targetAry[0], targetAry[1]);
    return val1 === val2 ? 0 : val1 > val1 ? 1 : -1;
  }

  function isSubtableTarget(target) {
    return target.indexOf(".") > -1;
  }

  function buildSubtableTargetAry(target, expectedLength = -1) {
    let targetAry = target.split(".");
    if (expectedLength !== -1 && targetAry.length !== expectedLength)
      throw `target ${target} does not have the expected length of ${expectedLength}`;
    return targetAry;
  }
  function addAutofillElement(target, imagePath) {
    const targetAry = buildSubtableTargetAry(target, 2);
    const node = `
            <button type="submit" title="Ganze Spalte mit letztem Wert ausfüllen" style="background-color:transparent; border-color:transparent;" onclick="evt.fill_column_${targetAry[1]}_onclick();"> 
                <img src="${imagePath}" height="16"/>
            </button>
        `;
    const element = document.createTextNode(node);
    const elem = document.getElementById(
      `#div_${targetAry[0]}_${targetAry[1]}_header`
    );
    if (elem) elem.appendChild(element);
  }

  function disableAllRequirements() {
    const elements = document.querySelectorAll('[class*="required"]');
    for (const element of elements)
      element.classList.remove("required", "jr-required-field", "one-required");
  }

  const logCfg = {
    global: {
      level: 1,
      style: "dark",
    },
    level: { SUCCESS: 1, DEBUG: 1, INFO: 2, WARN: 3, ERROR: 4 },
    style: {
      dark: {
        SUCCESS: "#009900",
        DEBUG: "#1a75ff",
        INFO: "#e6e6e6",
        WARN: "#ff9900",
        ERROR: "#ff0000",
        default: "color:",
      },
      light: {
        SUCCESS: "#008000",
        DEBUG: "#000099",
        INFO: "#505050",
        WARN: "#ff9900",
        ERROR: "#cc0000",
        default: "font-weight:bold;background-color:#F5F5F5;color:",
      },
    },
  };

  function log(level, title, obj) {
    if (logCfg.global.level > logCfg.level[level]) return;
    const style = logCfg.style[logCfg.global.style].default + logCfg.style[logCfg.global.style][level];
    if (obj) console.log("%c" + title, style, obj);
    else console.log("%c" + title, style);
  }

  function serializeSubtable(subtable, columns, asJSON) {
    const data = {};
    jr_loop_table(subtable, (subtable, rowId) => {
      data[rowId] = {};
      columns.forEach((column) => {
        data[rowId][column] = getValue(`${subtable}.${column}.${rowId}`);
      });
    });
    return asJSON ? JSON.stringify(data) : data;
  }

  function initializeEventListeners(events) {
    Object.keys(events).forEach((item) => {
      Object.keys(events[item]).forEach((evt) => {
        const elem = document.getElementById(item);
        if (elem) elem.addEventListener(evt, events[item][evt], false);
      });
    });
  }

  function fireEvent(name, target) {
    const evt = new Event(name, {"bubbles":true, "cancelable":false});
    document.getElementById(target).dispatchEvent(evt);
    evt.remove();
  }

  function test(desc, fn) {
    try {
      fn();
      console.log('\x1b[32m%s\x1b[0m', '\u2714 ' + desc);
    } catch (error) {
      console.log('\n');
      console.log('\x1b[31m%s\x1b[0m', '\u2718 ' + desc);
      console.error(error);
    }
  }
  
  function assert(isTrue) {
    if (!isTrue) { 
      throw new Error(); 
    }
  }

  function testFunc(testName, todo, params, expected) {
    let received;
    try {
      received = todo(params);
    } catch (e) {
      received = e;
    }
    const expectedType = typeof expected;
    const receivedType = typeof received;
    const logLevel = expected === received ? "SUCCESS" : "ERROR";
    log(
      logLevel,
      `${testName} -> expected: ${expected} [${expectedType}] | received: ${received} [${receivedType}]`
    );
  }

  function testReq(funcName, data) {
    const paramsStr = JSON.stringify(data.params);

    const success = (resultObj) => {
      const resultStr = JSON.stringify(resultObj.result.data);

      if (data.obj === resultStr) log("SUCCESS", funcName + " -> " + paramsStr);
      else {
        log("ERROR", funcName + " -> " + paramsStr, resultObj.result.data);
        //$u.log('INFO', resultObj.result.data);
      }
    };

    const error = (resultObj) => {
      log("ERROR", funcName + " -> " + paramsStr, resultObj);
    };

    request(funcName, data.params, success, error);
  }

  function request(
    functionId,
    userParameters,
    onSuccessCallback,
    onErrorCallback
  ) {
    var parameters = $("dialogForm").serialize(true);

    var url = "index.php?cmd=Ajax_ExecutePHPFunction";
    var postParameters = {
      functionId: functionId,
      workflowid: jQuery("#workflowid").val(),
      jr_simulation: jQuery("#jr_simulation").val(),
      dialogValues: parameters,
      userParameters: userParameters,
      ps: parameters.ps,
      username: parameters.username,
      do: parameters.do,
    };

    if (
      (parameters.ps || parameters.do) &&
      document.getElementById("jr_instance_id")
    )
      postParameters.jr_instance_id =
        document.getElementById("jr_instance_id").value;

    var onSucessCallbackWrapper = function (resultObject) {
      if (resultObject.status !== "error") {
        if (
          resultObject.result &&
          resultObject.result.__DialogFunctionClassSaveCalled__
        )
          jQuery("#jr_new_step").val(0);
        if (typeof onSuccessCallback === "function")
          onSuccessCallback(resultObject);
      } else {
        if (typeof onErrorCallback === "function")
          onErrorCallback(resultObject);
      }
    };

    jQuery
      .ajax({
        type: "POST",
        url: url,
        data: postParameters,
        success: function () {},
        dataType: "json",
      })
      .always(function () {
        window.resizeContent();
      })
      .done(onSucessCallbackWrapper)
      .fail(onErrorCallback);
  }

  function printPageLoadTime() {
    const loadTime = window.performance.timing.domContentLoadedEventEnd-window.performance.timing.navigationStart; 
    console.log('Page load time is '+ loadTime + 'ms.');
  }

  return {
    getFieldType: getFieldType,
    getValue: getValue,
    getValueN: getValueN,
    setValue: setValue,
    setValueN: setValueN,
    fixFloat: fixFloat,
    castValueToType: castValueToType,
    setFieldColor: setFieldColor,
    setCellStyle: setCellStyle,
    getDate: getDate,
    setProperty: setProperty,
    setProperties: setProperties,
    getType: getType,
    compareColumnSumToField: compareColumnSumToField,
    isSubtableTarget: isSubtableTarget,
    buildSubtableTargetAry: buildSubtableTargetAry,
    addAutofillElement: addAutofillElement,
    disableAllRequirements: disableAllRequirements,
    log: log,
    serializeSubtable: serializeSubtable,
    initializeEventListeners: initializeEventListeners,
    fireEvent: fireEvent,
    test: test,
    assert: assert,
    testFunc: testFunc,
    testReq: testReq,
    request: request,
    printPageLoadTime: printPageLoadTime
    //sendMail
    //startIncident
    //storeDocuments
  };
})();
