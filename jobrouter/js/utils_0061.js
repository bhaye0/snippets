"use strict";
// utils build 0061

/* global dayjs, model, 
   jr_get_value, jr_set_value, jr_get_subtable_value, jr_set_subtable_value, jr_loop_table, jr_get_subtable_row_ids, jr_sum_subtable_column
   jr_show, jr_hide, jr_set_required, jr_set_disabled, jr_set_readonly, jr_show_subtable_column, jr_hide_subtable_column
*/

/*
$u.getValue(target);
$u.setValue(target, value);
$u.setProperties(properties);
$u.getType(value);
$u.getDate(value, format);
$u.compareColumnSumToField(targetSt, targetField);
$u.addAutofillElement(target, imagePath);
$u.setCellStyle(target, cellStyle);
$u.disableAllRequirements();
$u.log(level, msg, obj);
//testFunc: (testName, todo, params, expected) => { $u.testFunc(testName, todo, params, expected); },
$u.testReq(funcName, data);
//testReq: $u.testReq,
$u.serializeSubtable(subtable, columns, asJSON);
$u.initializeEventListeners(events);
$u.isSubtableTarget(target);
$u.buildSubtableTargetAry(target, expectedLength);
$u.loadData(func, params, success, error);
%u.fixFloat(rawValue);
//sendMail
//startIncident
//storeDocuments
*/

var $u = {
    getValue: (target) => {
        const fieldType = model.get.fieldType(target);
        let rawValue;
        
        if ($u.isSubtableTarget(target)) {
            const targetAry = $u.buildSubtableTargetAry(target, 3);
            rawValue = jr_get_subtable_value(targetAry[0], targetAry[2], targetAry[1]);
        }
        else rawValue = jr_get_value(target);
        
        return $u.castValueToType(rawValue, fieldType);
    },
    getValueN: (target) => {
        const value = $u.getValue(target);
        return (value) ? value : null; 
    },
    setValueN: (target, value) => { $u.setValue(target, value, true); },
    setValue: (target, rawValue) => {
        const data = { };
        data.target = target;
        data.rawValue = rawValue;
        data.fieldType = model.get.fieldType(target);
        data.valueType = $u.getType(rawValue);

        let logLevel = (data.fieldType === data.valueType) ? 'DEBUG' : 'WARN';
        
        data.value = (data.fieldType === 'dayjs') ? rawValue.$d : ((data.fieldType === data.valueType) ? rawValue : '');

        $u.log(logLevel, '$u.setValue', data);
            
        if ($u.isSubtableTarget(target)) {
            const targetAry = $u.buildSubtableTargetAry(target, 3);
            if (targetAry[2] === "*") {
                jr_loop_table(targetAry[0], function (subtable, rowId) {
                    jr_set_subtable_value(subtable, rowId, targetAry[1], data.value);
                });
            }
            else jr_set_subtable_value(targetAry[0], targetAry[2], targetAry[1], data.value);
        }
        else jr_set_value(target, data.value);
    },
    fixFloat: (rawValue) => { return parseFloat(rawValue.toFixed(2)); },
    castValueToType: (rawValue, type) => {
        const castStringToGivenType = (rawValue, type) => {
            switch (type) {
                case 'string': return String(rawValue);
                case 'number': return parseFloat(rawValue);
                case 'dayjs': return dayjs(rawValue);
                case 'bool': return Boolean(rawValue);
                default: throw `unknown type ${type} given for value ${rawValue}`;
            }
        };

        let value;
        if (Array.isArray(rawValue)) {
            value = [];
            rawValue.forEach((item, index) => { value.push(castStringToGivenType(item, type)); });
        }
        else value = castStringToGivenType(rawValue, type);
        
        return value;
    },
    setFieldColor: (target, color) => {
        const exec = (myTarget) => { document.getElementById(myTarget).setAttribute("style", "background-color:" + color); };
    
        if ($u.isSubtableTarget(target)) {
            const targetAry = $u.buildSubtableTargetAry(target, 3);
            
            if (targetAry[2] === '*') {
                jr_loop_table(targetAry[0], (subtable, row) => { exec(`${subtable}_${targetAry[1]}_${row}`); });
            }
            else exec(`${targetAry[0]}_${targetAry[1]}_${targetAry[2]}`);
        }
        else exec(target);
    },
    setCellStyle: (target, cellStyle) => {
    },
    getDate: (value = 'NOW', format = 'YYYY-MM-DD HH:mm', strFormat = false) => {
        const myDate = (value === 'NOW') ? dayjs() : dayjs(value);
        return (strFormat) ? myDate.format(format) : myDate;
    },
    setProperties: (properties) => {
        for (const property in properties) {
            const type = $u.getType(properties[property]);

            switch (type) {
                case "string": $u.modifyPtProperty(property, properties[property]); break;
                case "array": properties[property].forEach(prop => {
                        $u.modifyPtProperty(property, prop); 
                    }); break;
                case "object": for (const field in properties[property]) {
                        $u.modifyStProperty(property, field, properties[property][field]); 
                    } break;
                default: throw (`Property of type ${type} can not be processed.`);
            }
        }
    },
    modifyPtProperty: (field, prop) => {  $u.ptPropertyModifier[prop](field); },
    modifyStProperty: (subtable, field, prop) => { 
        if ($u.getType(prop) === 'array') {
            prop.forEach(propEntry => { $u.stPropertyModifier[propEntry](subtable, field); });
        }
        else $u.stPropertyModifier[prop](subtable, field); 
    },
    ptPropertyModifier: {
        show: (field) => { jr_show(field); },
        hide: (field) => { jr_hide(field); },
        require: (field) => { jr_set_required(field, true); },
        unrequire: (field) => { jr_set_required(field, false); },
        enable: (field) => { jr_set_disabled(field, false); },
        disable: (field) => { jr_set_disabled(field, true); },
        empty: (field) => { jr_set_value(field, ""); },
        readonly: (field) => { jr_set_readonly(field, true); },
        readwrite: (field) => { jr_set_readonly(field, false); }
    },
    stPropertyModifier: {
        show: (subtable, field) => { jr_show_subtable_column(subtable, field); },
        hide: (subtable, field) => { jr_hide_subtable_column(subtable, field); },
        require: (subtable, field) => { $u.stPropertyModifierLib.required(subtable, field, true); },
        unrequire: (subtable, field) => { $u.stPropertyModifierLib.required(subtable, field, false); },
        enable: (subtable, field) => { $u.stPropertyModifierLib.active(subtable, field, false); },
        disable: (subtable, field) => { $u.stPropertyModifierLib.active(subtable, field, true); },
        empty: (subtable, field) => { $u.setValue(`${subtable}.${field}.*`, ""); },
        readonly: (subtable, field) => { $u.stPropertyModifierLib.writeable(subtable, field, true) },
        readwrite: (subtable, field) => { $u.stPropertyModifierLib.writeable(subtable, field, false) },
    },
    stPropertyModifierLib: {
        active: (subtable, field, value) => {
            const rows = jr_get_subtable_row_ids(subtable);
            rows.forEach((row) => { (document.getElementById(`${subtable}_${field}_${row}`)).disabled = value; });
        },
        writeable: (subtable, field, value) => {
            const rows = jr_get_subtable_row_ids(subtable);
            rows.forEach((row) => { jr_set_readonly(`${subtable}_${field}_${row}`, value); });
        },
        required: (subtable, field, value) => {
            const rows = jr_get_subtable_row_ids(subtable);
            rows.forEach((row) => { 
                const elem = document.getElementById(`${subtable}_${field}_${row}`);
                if (value) elem.classList.add('required');
                else elem.classList.remove('required');
            });
        },
    },
    getType: (value) => {
        if (typeof value == 'undefined' || value === null) return "null || undefined";

        const type = typeof value;
        if (type === 'object') {
            if (value instanceof dayjs)
                return "dayjs";
            if (Array.isArray(value))
                return "array";
        }
        return type;
    },
    compareColumnSumToField: (targetSt, targetField) => {
        const targetAry = $u.buildSubtableTargetAry(targetSt, 2);
        const val1 = $u.getValue(targetField, 'f');
        const val2 = jr_sum_subtable_column(targetAry[0], targetAry[1]);
        return (val1 === val2) ? 0 : (val1 > val1) ? 1 : -1;
    },
    isSubtableTarget: (target) => { return (target.indexOf('.') > -1); },
    buildSubtableTargetAry: (target, expectedLength = -1) => {
        let targetAry = target.split(".");
        if (expectedLength !== -1 && targetAry.length !== expectedLength) 
            throw (`target ${target} does not have the expected length of ${expectedLength}`);
        return targetAry; 

    },
    addAutofillElement: (target, imagePath) => {
        const targetAry = $u.buildSubtableTargetAry(target, 2);
        const node = `
            <button type="submit" title="Ganze Spalte mit letztem Wert ausfüllen" style="background-color:transparent; border-color:transparent;" onclick="evt.fill_column_${targetAry[1]}_onclick();"> 
                <img src="${imagePath}" height="16"/>
            </button>
        `;
        const element = document.createTextNode(node);
        const elem = document.getElementById(`#div_${targetAry[0]}_${targetAry[1]}_header`);
        if (elem)
            elem.appendChild(element);
    },
    disableAllRequirements: () => {
        const elements = document.querySelectorAll('[class*="required"]');
        for (const element of elements) element.classList.remove("required", "jr-required-field", "one-required");
    },
    log: (level, title, obj) => {
        if (log.cfg.level > log.level[level]) return;
        const style = log.style[log.cfg.style][level];
        if (obj) console.log("%c" + title, style, obj);
        else console.log("%c" + title, style);
    },
    serializeSubtable: (subtable, columns, asJSON) => {
        const data = {};
        jr_loop_table(subtable, (subtable, rowId) => {
            data[rowId] = {};
            columns.forEach(column => { data[rowId][column] = $u.getValue(`${subtable}.${column}.${rowId}`); });
        });
        return (asJSON) ? JSON.stringify(data) : data;
    },
    initializeEventListeners: (events) => {
        Object.keys(events).forEach(item => {
            Object.keys(events[item]).forEach(evt => {
                const elem = document.getElementById(item);
                if (elem)
                    elem.addEventListener(evt, events[item][evt], false);
            });
        });
    },
    testFunc: (testName, todo, params, expected) => {
        let received;
        try {
            received = todo(params);
        }
        catch (e) {
            received = e;
        }
        const expectedType = typeof expected;
        const receivedType = typeof received;
        const logLevel = (expected === received) ? 'SUCCESS' : 'ERROR';
        $u.log(logLevel, `${testName} -> expected: ${expected} [${expectedType}] | received: ${received} [${receivedType}]`);
    },
    testReq: (funcName, data) => {
        const paramsStr = JSON.stringify(data.params);
    
        const success = (resultObj) => {
            const resultStr = JSON.stringify(resultObj.result.data);
            
            if (data.obj === resultStr) $u.log('SUCCESS', funcName + " -> " + paramsStr);
            else {
                $u.log('ERROR', funcName + " -> " + paramsStr, resultObj.result.data);
                //$u.log('INFO', resultObj.result.data);
            }
        };
        
        const error = (resultObj) => { $u.log('ERROR', funcName + " -> " + paramsStr, resultObj); };
        
        $u.loadData(funcName, data.params, success, error);
    },
    loadData: (functionId, userParameters, onSuccessCallback, onErrorCallback) => {
        var parameters = $("dialogForm").serialize(true);
    
        var url = "index.php?cmd=Ajax_ExecutePHPFunction";
        var postParameters = {
            "functionId": functionId,
            "workflowid": jQuery("#workflowid").val(),
            "jr_simulation": jQuery("#jr_simulation").val(),
            "dialogValues": parameters,
            "userParameters": userParameters,
            "ps": parameters.ps,
            "username": parameters.username,
            "do": parameters.do
        };
    
        if ((parameters.ps || parameters.do) && document.getElementById('jr_instance_id')) 
            postParameters.jr_instance_id = document.getElementById('jr_instance_id').value;
    
        var onSucessCallbackWrapper = function(resultObject) {
            if (resultObject.status !== "error") {
                if (resultObject.result && resultObject.result.__DialogFunctionClassSaveCalled__) jQuery("#jr_new_step").val(0);
                if (typeof onSuccessCallback === "function") onSuccessCallback(resultObject);
            } 
            else { if (typeof onErrorCallback === "function") onErrorCallback(resultObject); }
        };
    
        jQuery.ajax({
            type: "POST",
            url: url,
            data: postParameters,
            success: function() { },
            dataType: "json"
        }).always(function() {
            window.resizeContent();
        }).done(onSucessCallbackWrapper).fail(onErrorCallback);
    }
};

var log = {
    cfg: {
        level: 1,
        style: "dark"
    },
    level: {
        SUCCESS: 1,
        DEBUG: 1,
        INFO: 2,
        WARN: 3,
        ERROR: 4
    },
    style: {
        dark: {
            SUCCESS: "color:#009900",
            DEBUG: "color:#1a75ff",
            INFO: "color:#e6e6e6",
            WARN: "color:#ff9900",
            ERROR: "color:#ff0000"
        },
        light: {
            SUCCESS: "font-weight:bold;background-color:#F5F5F5;color:#008000",
            DEBUG: "font-weight:bold;background-color:#F5F5F5;color:#000099",
            INFO: "font-weight:bold;background-color:#F5F5F5;color:#505050",
            WARN: "font-weight:bold;background-color:#F5F5F5;color:#ff9900",
            ERROR: "font-weight:bold;background-color:#F5F5F5;color:#cc0000"
        },
        mode: "dark"
    }
};